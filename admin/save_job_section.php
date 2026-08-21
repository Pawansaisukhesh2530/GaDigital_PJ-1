<?php
/**
 * save_job_section.php
 * ---------------------------------------------------------------------------
 * Server-side handler for the Single-Page Editor section saves.
 *
 * Each section of the editor POSTs only its own fields. This handler:
 *   1. Validates CSRF + POST method
 *   2. Determines which fields belong to the submitted section (1–8)
 *   3. Validates only those fields (scoped validation)
 *   4. Builds a partial UPDATE touching only the section's columns
 *   5. Redirects back with flash=saved on success, or errors on failure
 *
 * NEVER creates new job records — always UPDATE on an existing row.
 * Reuses: cpvia_validate_job(), cpvia_job_param_map(), cpvia_replace_job_skills(),
 *         cpvia_compose_location(), cpvia_collect_job_post(), cpvia_sanitize_richtext()
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/job_helpers.php';
require_once __DIR__ . '/../settings_helpers.php';

// ─── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

// ─── CSRF check ───────────────────────────────────────────────────────────────
if (!cpvia_csrf_check($_POST['csrf_token'] ?? null)) {
    $_SESSION['section_error'] = 'Your session has expired. Please refresh and try again.';
    $redir_id = (int) ($_POST['id'] ?? 0);
    $redir_sec = (int) ($_POST['section'] ?? 1);
    header('Location: edit_job_single.php?id=' . $redir_id . '&section=' . $redir_sec . '&errors=1');
    exit;
}

// ─── Read section & job ID ────────────────────────────────────────────────────
$section = (int) ($_POST['section'] ?? 0);
$job_id  = (int) ($_POST['id'] ?? 0);

if ($section < 1 || $section > 4 || $job_id <= 0) {
    header('Location: jobs.php');
    exit;
}

// ─── Database connection ──────────────────────────────────────────────────────
$db_file = __DIR__ . '/cpvia_database.sqlite';
$pdo = cpvia_db($db_file);

// ─── Fetch existing job row (must exist — UPDATE only, never INSERT) ──────────
try {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    header('Location: jobs.php');
    exit;
}
if (!$job) {
    header('Location: jobs.php');
    exit;
}

// ─── Section → field mapping (4 consolidated sections) ────────────────────────
$section_fields = [
    1 => ['title', 'department', 'job_code', 'employment_type', 'work_mode', 'number_of_openings', 'hiring_priority',
          'country', 'state', 'city', 'office_location', 'remote_available'],
    2 => ['min_experience', 'max_experience', 'minimum_qualification', 'degree', 'specialization',
          'salary_type', 'min_salary', 'max_salary', 'currency'],  // skills also handled separately
    3 => ['description', 'responsibilities', 'requirements', 'benefits'],
    4 => ['preferred_notice_period', 'gender_preference', 'minimum_age', 'maximum_age',
          'submission_mode', 'recipient_emails'],
];

$fields = $section_fields[$section];
$rich_fields = ['description', 'responsibilities', 'requirements', 'benefits'];

// ─── Collect submitted values for this section only ───────────────────────────
// Start from existing job data so untouched fields are preserved.
$values = cpvia_job_row_to_values($job);

foreach ($fields as $f) {
    if ($f === 'remote_available') {
        $values['remote_available'] = isset($_POST['remote_available']) ? 1 : 0;
        continue;
    }
    if (in_array($f, $rich_fields, true)) {
        $values[$f] = cpvia_sanitize_richtext($_POST[$f] ?? '');
    } else {
        $values[$f] = trim((string) ($_POST[$f] ?? ''));
    }
}

// Handle defaults for specific fields
if ($section === 1) {
    if ($values['number_of_openings'] === '') {
        $values['number_of_openings'] = '1';
    }
}
if ($section === 4) {
    if ($values['gender_preference'] === '') {
        $values['gender_preference'] = 'Any';
    }
    if (!in_array($values['submission_mode'], cpvia_submission_modes(), true)) {
        $values['submission_mode'] = 'BACKEND_ONLY';
    }
    if (!cpvia_mode_needs_email($values['submission_mode'])) {
        $values['recipient_emails'] = '';
    }
}

// Parse skill IDs for section 2
$required_skills = [];
$preferred_skills = [];
if ($section === 2) {
    $required_skills = cpvia_parse_skill_ids($_POST['required_skills'] ?? '');
    $preferred_skills = cpvia_parse_skill_ids($_POST['preferred_skills'] ?? '');
}

// ─── Scoped validation ────────────────────────────────────────────────────────
$error = '';

// Section 1: title always required + job code uniqueness
if ($section === 1) {
    if ($values['title'] === '') {
        $error = 'A job title is required, even to save a draft.';
    }
    if ($error === '' && $values['job_code'] !== '') {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE job_code = ? AND id <> ?");
        $chk->execute([$values['job_code'], $job_id]);
        if ((int) $chk->fetchColumn() > 0) {
            $error = 'That Job Code is already used by another job. Please choose a unique code.';
        }
    }
}

// Section 2: experience + salary range validation
if ($section === 2 && $error === '') {
    $numOrNull = static fn($v) => ($v !== '' && is_numeric($v)) ? (float) $v : null;
    $minE = $numOrNull($values['min_experience']);
    $maxE = $numOrNull($values['max_experience']);
    if ($minE !== null && $maxE !== null && $maxE < $minE) {
        $error = 'Maximum experience cannot be lower than minimum experience.';
    }
    if ($error === '') {
        $minS = $numOrNull($values['min_salary']);
        $maxS = $numOrNull($values['max_salary']);
        if ($minS !== null && $maxS !== null && $maxS < $minS) {
            $error = 'Maximum salary cannot be lower than minimum salary.';
        }
    }
}

// Section 4: age range + submission mode validation
if ($section === 4 && $error === '') {
    $numOrNull = static fn($v) => ($v !== '' && is_numeric($v)) ? (float) $v : null;
    $minA = $numOrNull($values['minimum_age']);
    $maxA = $numOrNull($values['maximum_age']);
    if ($minA !== null && $maxA !== null && $maxA < $minA) {
        $error = 'Maximum age cannot be lower than minimum age.';
    }
    if ($error === '' && !in_array($values['submission_mode'], cpvia_submission_modes(), true)) {
        $error = 'Please choose a valid Application Delivery option.';
    }
    if ($error === '' && cpvia_mode_needs_email($values['submission_mode'])) {
        $raw = trim($values['recipient_emails']);
        if ($raw !== '') {
            $parsed = cpvia_parse_email_list($raw);
            if (!$parsed['ok']) {
                $error = $parsed['error'];
            }
        }
    }
}

// ─── Handle validation failure ────────────────────────────────────────────────
if ($error !== '') {
    $_SESSION['section_error'] = $error;
    $_SESSION['section_values'] = [];
    // Store submitted values for the section so the form can repopulate them
    foreach ($fields as $f) {
        $_SESSION['section_values'][$f] = $values[$f];
    }
    if ($section === 2) {
        $_SESSION['section_values']['required_skills'] = implode(',', $required_skills);
        $_SESSION['section_values']['preferred_skills'] = implode(',', $preferred_skills);
    }
    header('Location: edit_job_single.php?id=' . $job_id . '&section=' . $section . '&errors=1');
    exit;
}

// ─── Build partial UPDATE (only this section's columns) ───────────────────────
$numOrNull = static fn($v) => ($v === '' || $v === null) ? null : $v;
$set_clauses = [];
$params = [];

foreach ($fields as $f) {
    $col = $f;
    $placeholder = ':' . $f;

    switch ($f) {
        case 'remote_available':
            $params[$placeholder] = (int) $values[$f];
            break;
        case 'number_of_openings':
            $params[$placeholder] = (int) ($values[$f] === '' ? 1 : $values[$f]);
            break;
        case 'gender_preference':
            $params[$placeholder] = $values[$f] === '' ? 'Any' : $values[$f];
            break;
        case 'submission_mode':
            $params[$placeholder] = in_array($values[$f], cpvia_submission_modes(), true)
                ? $values[$f] : 'BACKEND_ONLY';
            break;
        case 'recipient_emails':
            if (!cpvia_mode_needs_email($values['submission_mode'] ?? 'BACKEND_ONLY')) {
                $params[$placeholder] = null;
            } else {
                $parsed = cpvia_parse_email_list($values[$f] ?? '');
                $params[$placeholder] = ($parsed['ok'] && $parsed['normalized'] !== '') ? $parsed['normalized'] : null;
            }
            break;
        default:
            $params[$placeholder] = $numOrNull($values[$f]);
            break;
    }

    $set_clauses[] = "$col = $placeholder";
}

// Section 1: also update the computed `location` column
if ($section === 1) {
    $location = cpvia_compose_location(
        $values['city'],
        $values['state'],
        $values['country'],
        $values['office_location']
    );
    // Preserve legacy location if granular fields are all empty
    if ($location === '' && !empty($job['location'])) {
        $location = (string) $job['location'];
    }
    $set_clauses[] = "location = :location";
    $params[':location'] = $location;
}

// Always set updated_at
$set_clauses[] = "updated_at = :updated_at";
$params[':updated_at'] = date('Y-m-d H:i:s');

// WHERE clause
$params[':id'] = $job_id;

$sql = "UPDATE jobs SET " . implode(', ', $set_clauses) . " WHERE id = :id";

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Section 2: also update skills
    if ($section === 2) {
        cpvia_replace_job_skills($pdo, $job_id, $required_skills, 'required');
        cpvia_replace_job_skills($pdo, $job_id, $preferred_skills, 'preferred');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['section_error'] = 'Database error: ' . $e->getMessage();
    header('Location: edit_job_single.php?id=' . $job_id . '&section=' . $section . '&errors=1');
    exit;
}

// ─── Success: redirect with flash ────────────────────────────────────────────
// Clear any leftover error state
unset($_SESSION['section_error'], $_SESSION['section_values']);

header('Location: edit_job_single.php?id=' . $job_id . '&section=' . $section . '&flash=saved');
exit;
