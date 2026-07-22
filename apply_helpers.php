<?php
/**
 * apply_helpers.php
 * -----------------------------------------------------------------------------
 * Reusable, self-contained helpers for the public 7-step Candidate Application
 * Wizard. No hardcoded absolute paths — callers pass the PDO connection and
 * directories. Designed to be portable into another CPVIA project folder.
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/settings_helpers.php';

if (!function_exists('cpvia_apply_employment_statuses')) {
    function cpvia_apply_employment_statuses(): array
    {
        return ['Working', 'Serving Notice', 'Freelancer', 'Student', 'Unemployed'];
    }
}

if (!function_exists('cpvia_apply_qualifications')) {
    function cpvia_apply_qualifications(): array
    {
        return ['Diploma', "Bachelor's", "Master's", 'PhD', 'Other'];
    }
}

if (!function_exists('cpvia_apply_currencies')) {
    function cpvia_apply_currencies(): array
    {
        return ['INR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED'];
    }
}

/** Allowed document uploads: extension => list of acceptable detected MIME types. */
if (!function_exists('cpvia_apply_allowed_docs')) {
    function cpvia_apply_allowed_docs(): array
    {
        return [
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword', 'application/octet-stream'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/octet-stream',
            ],
        ];
    }
}

if (!defined('CPVIA_APPLY_MAX_UPLOAD')) {
    define('CPVIA_APPLY_MAX_UPLOAD', 5 * 1024 * 1024); // 5 MB
}
if (!defined('CPVIA_APPLY_TEXT_MAX')) {
    define('CPVIA_APPLY_TEXT_MAX', 2000);
}

/**
 * Fetch a publicly-applyable job by id. Returns the row only when the job is
 * visible on Careers (Active/Published). Returns null otherwise.
 */
if (!function_exists('cpvia_apply_fetch_job')) {
    function cpvia_apply_fetch_job(PDO $pdo, int $jobId): ?array
    {
        if ($jobId <= 0) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND status IN ('Active','Published')");
            $stmt->execute([$jobId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

/** Active master skills for the chip selector. */
if (!function_exists('cpvia_apply_fetch_skills')) {
    function cpvia_apply_fetch_skills(PDO $pdo): array
    {
        try {
            $rows = $pdo->query("SELECT id, name FROM skills WHERE is_active = 1 ORDER BY name COLLATE NOCASE ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
        return array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $rows);
    }
}

/**
 * Securely validate and store an uploaded document.
 * @return array{ok:bool, error:string, stored?:string, original?:string, mime?:string, size?:int, path?:string}
 */
if (!function_exists('cpvia_apply_store_upload')) {
    function cpvia_apply_store_upload(array $file, string $uploadDir, string $prefix, bool $required): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($err === UPLOAD_ERR_NO_FILE) {
            return $required
                ? ['ok' => false, 'error' => 'This document is required.']
                : ['ok' => true, 'error' => '', 'stored' => null];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'File upload failed. Please try again.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Invalid upload source.'];
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > CPVIA_APPLY_MAX_UPLOAD) {
            return ['ok' => false, 'error' => 'File must be between 1 byte and ' . (CPVIA_APPLY_MAX_UPLOAD / (1024 * 1024)) . ' MB.'];
        }

        $original = (string) ($file['name'] ?? 'document');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = cpvia_apply_allowed_docs();

        // Reject unknown extensions and double-extension attacks (only the final
        // extension is considered; e.g. resume.pdf.php -> ext "php" -> rejected).
        if (!array_key_exists($ext, $allowed)) {
            return ['ok' => false, 'error' => 'Only PDF, DOC or DOCX files are allowed.'];
        }

        // Verify the real MIME type from file contents.
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        if ($mime !== '' && !in_array($mime, $allowed[$ext], true)) {
            return ['ok' => false, 'error' => 'The file content does not match an allowed document type.'];
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return ['ok' => false, 'error' => 'Server could not prepare the upload directory.'];
        }

        // Safe, unique, non-guessable filename. Never reuse the candidate's name.
        $stored = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $stored;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }

        return [
            'ok' => true,
            'error' => '',
            'stored' => $stored,
            'original' => mb_substr($original, 0, 255),
            'mime' => $mime !== '' ? $mime : 'application/octet-stream',
            'size' => (int) $file['size'],
            'path' => $target,
        ];
    }
}

/**
 * Full server-side validation of the submitted application (excluding files,
 * which are validated during upload). Returns [errors[], clean[]].
 */
if (!function_exists('cpvia_apply_validate')) {
    function cpvia_apply_validate(array $post): array
    {
        $errors = [];
        $clean = [];

        $str = static fn($k, $max = 255) => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);

        // ---- Step 1: Personal ----
        $clean['full_name'] = $str('full_name', 120);
        if ($clean['full_name'] === '') {
            $errors['full_name'] = 'Full name is required.';
        }

        $clean['email'] = $str('email', 190);
        if (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }

        $clean['mobile'] = $str('mobile', 20);
        if ($clean['mobile'] === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $clean['mobile'])) {
            $errors['mobile'] = 'A valid mobile number is required.';
        }

        $clean['current_location'] = $str('current_location', 160);
        if ($clean['current_location'] === '') {
            $errors['current_location'] = 'Current location is required.';
        }

        $normUrl = static function (string $v): string {
            $v = trim($v);
            if ($v === '') {
                return '';
            }
            if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $v)) {
                $v = 'https://' . ltrim($v, '/');
            }
            return $v;
        };

        $clean['linkedin_profile'] = $str('linkedin_profile', 255);
        if ($clean['linkedin_profile'] !== '') {
            $clean['linkedin_profile'] = $normUrl($clean['linkedin_profile']);
            if (!filter_var($clean['linkedin_profile'], FILTER_VALIDATE_URL)) {
                $errors['linkedin_profile'] = 'Enter a valid URL or leave it blank.';
            }
        }

        // ---- Step 2: Professional ----
        $numOrNull = static function ($k) use ($post) {
            $v = trim((string) ($post[$k] ?? ''));
            return $v === '' ? null : $v;
        };

        $te = $numOrNull('total_experience');
        if ($te === null || !is_numeric($te) || (float) $te < 0 || (float) $te > 60) {
            $errors['total_experience'] = 'Enter total experience in years (0–60).';
            $clean['total_experience'] = null;
        } else {
            $clean['total_experience'] = (float) $te;
        }

        $re = $numOrNull('relevant_experience');
        if ($re === null || !is_numeric($re) || (float) $re < 0 || (float) $re > 60) {
            $errors['relevant_experience'] = 'Enter relevant experience in years (0–60).';
            $clean['relevant_experience'] = null;
        } else {
            $clean['relevant_experience'] = (float) $re;
            if (isset($clean['total_experience']) && $clean['relevant_experience'] > $clean['total_experience']) {
                $errors['relevant_experience'] = 'Relevant experience cannot exceed total experience.';
            }
        }

        $clean['current_company'] = $str('current_company', 160);
        $clean['current_designation'] = $str('current_designation', 160);

        foreach (['current_ctc', 'expected_ctc'] as $k) {
            $v = $numOrNull($k);
            if ($v === null) {
                $clean[$k] = null;
            } elseif (!is_numeric($v) || (float) $v < 0) {
                $errors[$k] = 'CTC cannot be negative.';
                $clean[$k] = null;
            } else {
                $clean[$k] = (float) $v;
            }
        }

        $currencies = cpvia_apply_currencies();
        $clean['ctc_currency'] = $str('ctc_currency', 8);
        if ($clean['ctc_currency'] === '' || !in_array($clean['ctc_currency'], $currencies, true)) {
            $clean['ctc_currency'] = 'INR';
        }

        $clean['notice_period'] = $str('notice_period', 60);
        if ($clean['notice_period'] === '') {
            $errors['notice_period'] = 'Notice period is required.';
        }

        $clean['employment_status'] = $str('employment_status', 40);
        if (!in_array($clean['employment_status'], cpvia_apply_employment_statuses(), true)) {
            $errors['employment_status'] = 'Select a valid employment status.';
        }

        // ---- Step 3: Education ----
        $clean['qualification'] = $str('qualification', 40);
        if (!in_array($clean['qualification'], cpvia_apply_qualifications(), true)) {
            $errors['qualification'] = 'Select your highest qualification.';
        }
        $clean['specialization'] = $str('specialization', 120);
        $clean['university_college'] = $str('university_college', 160);

        $gy = trim((string) ($post['graduation_year'] ?? ''));
        $maxYear = (int) date('Y') + 6;
        if ($gy === '') {
            $clean['graduation_year'] = null;
        } elseif (!ctype_digit($gy) || (int) $gy < 1950 || (int) $gy > $maxYear) {
            $errors['graduation_year'] = 'Enter a valid graduation year (1950–' . $maxYear . ').';
            $clean['graduation_year'] = null;
        } else {
            $clean['graduation_year'] = (int) $gy;
        }

        // ---- Step 4: Portfolio (files validated separately) ----
        $clean['portfolio_url'] = $str('portfolio_url', 255);
        if ($clean['portfolio_url'] !== '') {
            $clean['portfolio_url'] = $normUrl($clean['portfolio_url']);
            if (!filter_var($clean['portfolio_url'], FILTER_VALIDATE_URL)) {
                $errors['portfolio_url'] = 'Enter a valid portfolio URL or leave it blank.';
            }
        }

        // ---- Step 5: Skills ----
        $clean['skills'] = [];
        $rawSkills = $post['skills'] ?? '';
        if (is_string($rawSkills)) {
            $rawSkills = array_filter(array_map('trim', explode(',', $rawSkills)), static fn($v) => $v !== '');
        }
        if (is_array($rawSkills)) {
            $clean['skills'] = array_values(array_unique(array_map('intval', $rawSkills)));
        }

        // ---- Step 6: Additional questions ----
        $clean['why_interested'] = $str('why_interested', CPVIA_APPLY_TEXT_MAX);
        $clean['why_cpvia'] = $str('why_cpvia', CPVIA_APPLY_TEXT_MAX);

        $wtr = (string) ($post['willing_to_relocate'] ?? '');
        if ($wtr !== '0' && $wtr !== '1') {
            $errors['willing_to_relocate'] = 'Please indicate whether you are willing to relocate.';
            $clean['willing_to_relocate'] = 0;
        } else {
            $clean['willing_to_relocate'] = (int) $wtr;
        }

        // ---- Step 7: Declarations ----
        $clean['declaration_accurate'] = !empty($post['declaration_accurate']) ? 1 : 0;
        if ($clean['declaration_accurate'] !== 1) {
            $errors['declaration_accurate'] = 'You must confirm the information is accurate.';
        }
        $clean['consent_data_storage'] = !empty($post['consent_data_storage']) ? 1 : 0;
        if ($clean['consent_data_storage'] !== 1) {
            $errors['consent_data_storage'] = 'You must consent to data storage for recruitment.';
        }

        return [$errors, $clean];
    }
}

/** Which wizard step (1-based) a given field belongs to — used to jump to errors. */
if (!function_exists('cpvia_apply_field_step')) {
    function cpvia_apply_field_step(string $field): int
    {
        $map = [
            // Step 1 — Resume & documents (first, ready for future auto-fill)
            'resume' => 1, 'cover_letter_file' => 1, 'portfolio_url' => 1,
            // Step 2 — Personal
            'full_name' => 2, 'email' => 2, 'mobile' => 2, 'current_location' => 2, 'linkedin_profile' => 2,
            // Step 3 — Professional
            'total_experience' => 3, 'relevant_experience' => 3, 'current_company' => 3, 'current_designation' => 3,
            'current_ctc' => 3, 'expected_ctc' => 3, 'ctc_currency' => 3, 'notice_period' => 3, 'employment_status' => 3,
            // Step 4 — Education
            'qualification' => 4, 'specialization' => 4, 'university_college' => 4, 'graduation_year' => 4,
            // Step 5 — Skills
            'skills' => 5,
            // Step 6 — Additional questions
            'why_interested' => 6, 'why_cpvia' => 6, 'willing_to_relocate' => 6,
            // Step 7 — Review & declaration
            'declaration_accurate' => 7, 'consent_data_storage' => 7,
        ];
        return $map[$field] ?? 1;
    }
}

/* ============================================================================
 * APPLICATION DELIVERY — pending email review workflow
 * ==========================================================================*/

/** Allowed per-job submission modes. */
if (!function_exists('cpvia_apply_submission_modes')) {
    function cpvia_apply_submission_modes(): array
    {
        return ['BACKEND_ONLY', 'EMAIL_ONLY', 'BACKEND_AND_EMAIL'];
    }
}

/** Normalise a job's submission mode to a known value. */
if (!function_exists('cpvia_apply_normalize_mode')) {
    function cpvia_apply_normalize_mode(?string $mode): string
    {
        $mode = (string) $mode;
        return in_array($mode, cpvia_apply_submission_modes(), true) ? $mode : 'BACKEND_ONLY';
    }
}

/**
 * Build the placeholder data map (without braces) used to render the email
 * subject/body templates for an application.
 *
 * @param array $clean validated candidate data from cpvia_apply_validate()
 * @param array $job   the jobs row
 */
if (!function_exists('cpvia_apply_email_data')) {
    function cpvia_apply_email_data(PDO $pdo, array $clean, array $job, array $extra = []): array
    {
        $company = cpvia_get_setting($pdo, 'company_name', 'CPVIA');

        $expYears = (isset($clean['total_experience']) && $clean['total_experience'] !== null && $clean['total_experience'] !== '')
            ? rtrim(rtrim((string) $clean['total_experience'], '0'), '.') . ' years'
            : '';

        // Resolve selected skill IDs to names for the bullet list.
        $skillNames = [];
        if (!empty($clean['skills']) && is_array($clean['skills'])) {
            $ids = array_values(array_filter(array_map('intval', $clean['skills']), static fn($i) => $i > 0));
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                try {
                    $stmt = $pdo->prepare("SELECT name FROM skills WHERE id IN ($in) ORDER BY name COLLATE NOCASE ASC");
                    $stmt->execute($ids);
                    $skillNames = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                } catch (Throwable $e) {
                    $skillNames = [];
                }
            }
        }

        // Structured data used to render the professional summary block.
        $structured = [
            'application_ref'     => (string) ($extra['application_ref'] ?? ''),
            'submission_date'     => (string) ($extra['submission_date'] ?? date('F j, Y')),
            'job_title'           => (string) ($job['title'] ?? ''),
            'company_name'        => (string) $company,
            'candidate_name'      => (string) ($clean['full_name'] ?? ''),
            'candidate_email'     => (string) ($clean['email'] ?? ''),
            'candidate_phone'     => (string) ($clean['mobile'] ?? ''),
            'candidate_location'  => (string) ($clean['current_location'] ?? ''),
            'current_designation' => (string) ($clean['current_designation'] ?? ''),
            'current_company'     => (string) ($clean['current_company'] ?? ''),
            'total_experience'    => $expYears,
            'qualification'       => (string) ($clean['qualification'] ?? ''),
            'specialization'      => (string) ($clean['specialization'] ?? ''),
            'university_college'  => (string) ($clean['university_college'] ?? ''),
            'graduation_year'     => (string) ($clean['graduation_year'] ?? ''),
            'skills'              => $skillNames,
            'languages'           => is_array($clean['languages'] ?? null) ? $clean['languages'] : [],
            'linkedin'            => (string) ($clean['linkedin_profile'] ?? ''),
            'portfolio'           => (string) ($clean['portfolio_url'] ?? ''),
            'why_interested'      => (string) ($clean['why_interested'] ?? ''),
            'why_cpvia'           => (string) ($clean['why_cpvia'] ?? ''),
            'resume_name'         => (string) ($extra['resume_name'] ?? ''),
            'cover_name'          => (string) ($extra['cover_name'] ?? ''),
        ];

        $summary = cpvia_apply_build_summary($structured);

        // Flat placeholder map (used by the subject/body templates).
        return [
            'application_id'      => $structured['application_ref'],
            'submission_date'     => $structured['submission_date'],
            'candidate_name'      => $structured['candidate_name'],
            'candidate_email'     => $structured['candidate_email'],
            'candidate_phone'     => $structured['candidate_phone'],
            'candidate_location'  => $structured['candidate_location'],
            'job_title'           => $structured['job_title'],
            'job_code'            => (string) ($job['job_code'] ?? ''),
            'department'          => (string) ($job['department'] ?? ''),
            'company_name'        => $structured['company_name'],
            'total_experience'    => $structured['total_experience'],
            'current_company'     => $structured['current_company'],
            'current_designation' => $structured['current_designation'],
            'application_summary' => $summary,
        ];
    }
}

/**
 * Build the professional, sectioned "application summary" block (plain text).
 * Empty sections are skipped so the email stays clean. Used as the
 * {application_summary} placeholder inside the configurable body template.
 *
 * @param array $d structured data from cpvia_apply_email_data()
 */
if (!function_exists('cpvia_apply_build_summary')) {
    function cpvia_apply_build_summary(array $d): string
    {
        $W = 60;
        $rule = str_repeat('=', $W);
        $thin = str_repeat('-', $W);
        $lines = [];

        // key: value formatter with aligned labels
        $kv = static function (string $label, string $value) {
            $value = trim($value);
            if ($value === '') { return null; }
            return sprintf('%-17s: %s', $label, $value);
        };
        $addSection = static function (string $title, array $rows) use (&$lines, $thin) {
            $rows = array_values(array_filter($rows, static fn($r) => $r !== null && $r !== ''));
            if (empty($rows)) { return; }
            $lines[] = '';
            $lines[] = $thin;
            $lines[] = strtoupper($title);
            $lines[] = $thin;
            foreach ($rows as $r) { $lines[] = $r; }
        };

        // ---- Header ----
        $lines[] = $rule;
        $lines[] = 'APPLICATION SUMMARY';
        $lines[] = $rule;
        foreach ([
            $kv('Application ID', $d['application_ref']),
            $kv('Submission Date', $d['submission_date']),
            $kv('Job Title', $d['job_title']),
            $kv('Company', $d['company_name']),
        ] as $r) { if ($r !== null) { $lines[] = $r; } }

        // ---- Candidate Information ----
        $addSection('Candidate Information', [
            $kv('Full Name', $d['candidate_name']),
            $kv('Email', $d['candidate_email']),
            $kv('Phone', $d['candidate_phone']),
            $kv('Current Location', $d['candidate_location']),
        ]);

        // ---- Professional Summary ----
        $addSection('Professional Summary', [
            $kv('Current Position', $d['current_designation']),
            $kv('Current Company', $d['current_company']),
            $kv('Total Experience', $d['total_experience']),
        ]);

        // ---- Education ----
        $degree = trim($d['qualification'] . ($d['specialization'] !== '' ? ' (' . $d['specialization'] . ')' : ''));
        $addSection('Education', [
            $kv('Degree', $degree),
            $kv('Institution', $d['university_college']),
            $kv('Graduation Year', $d['graduation_year']),
        ]);

        // ---- Key Skills (bullet list) ----
        if (!empty($d['skills'])) {
            $lines[] = '';
            $lines[] = $thin;
            $lines[] = 'KEY SKILLS';
            $lines[] = $thin;
            foreach ($d['skills'] as $s) {
                $s = trim((string) $s);
                if ($s !== '') { $lines[] = '  - ' . $s; }
            }
        }

        // ---- Languages (optional) ----
        if (!empty($d['languages'])) {
            $lines[] = '';
            $lines[] = $thin;
            $lines[] = 'LANGUAGES';
            $lines[] = $thin;
            foreach ($d['languages'] as $l) {
                $l = trim((string) $l);
                if ($l !== '') { $lines[] = '  - ' . $l; }
            }
        }

        // ---- Portfolio & Links ----
        $linkRows = [];
        if (trim($d['linkedin']) !== '') { $linkRows[] = $kv('LinkedIn', $d['linkedin']); }
        if (trim($d['portfolio']) !== '') {
            if (stripos($d['portfolio'], 'github.com') !== false) {
                $linkRows[] = $kv('GitHub', $d['portfolio']);
            } else {
                $linkRows[] = $kv('Portfolio', $d['portfolio']);
            }
        }
        $addSection('Portfolio & Links', $linkRows);

        // ---- Cover Letter / Message ----
        $msgParts = [];
        if (trim($d['why_interested']) !== '') {
            $msgParts[] = 'Why interested in this role:' . "\n" . trim($d['why_interested']);
        }
        if (trim($d['why_cpvia']) !== '') {
            $msgParts[] = 'Why ' . $d['company_name'] . ':' . "\n" . trim($d['why_cpvia']);
        }
        if (!empty($msgParts)) {
            $lines[] = '';
            $lines[] = $thin;
            $lines[] = 'COVER LETTER / MESSAGE';
            $lines[] = $thin;
            $lines[] = implode("\n\n", $msgParts);
        }

        // ---- Attachments ----
        $attRows = [];
        if (trim($d['resume_name']) !== '') { $attRows[] = '  - Resume: ' . trim($d['resume_name']); }
        if (trim($d['cover_name']) !== '')  { $attRows[] = '  - Cover Letter: ' . trim($d['cover_name']); }
        if (!empty($attRows)) {
            $lines[] = '';
            $lines[] = $thin;
            $lines[] = 'ATTACHMENTS';
            $lines[] = $thin;
            foreach ($attRows as $r) { $lines[] = $r; }
        }

        $lines[] = '';
        $lines[] = $rule;

        return implode("\n", $lines);
    }
}

/**
 * Create a durable pending email application record and return its token.
 * The token is unguessable and used as the review-page key.
 *
 * @param array $data associative row data (see keys below)
 */
if (!function_exists('cpvia_create_pending_email')) {
    function cpvia_create_pending_email(PDO $pdo, array $data): string
    {
        $token = bin2hex(random_bytes(24));
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + 86400); // 24h to complete review

        $stmt = $pdo->prepare(
            "INSERT INTO pending_email_applications
                (token, job_id, application_id, mode, recipient_emails,
                 candidate_name, candidate_email, candidate_phone, job_title,
                 subject, body, resume_path, resume_original, cover_path, cover_original,
                 payload, status, attempts, created_at, updated_at, expires_at)
             VALUES
                (:token, :job_id, :application_id, :mode, :recipient_emails,
                 :candidate_name, :candidate_email, :candidate_phone, :job_title,
                 :subject, :body, :resume_path, :resume_original, :cover_path, :cover_original,
                 :payload, 'pending', 0, :created_at, :updated_at, :expires_at)"
        );
        $stmt->execute([
            ':token' => $token,
            ':job_id' => $data['job_id'] ?? null,
            ':application_id' => $data['application_id'] ?? null,
            ':mode' => $data['mode'],
            ':recipient_emails' => $data['recipient_emails'],
            ':candidate_name' => $data['candidate_name'] ?? null,
            ':candidate_email' => $data['candidate_email'] ?? null,
            ':candidate_phone' => $data['candidate_phone'] ?? null,
            ':job_title' => $data['job_title'] ?? null,
            ':subject' => $data['subject'] ?? null,
            ':body' => $data['body'] ?? null,
            ':resume_path' => $data['resume_path'] ?? null,
            ':resume_original' => $data['resume_original'] ?? null,
            ':cover_path' => $data['cover_path'] ?? null,
            ':cover_original' => $data['cover_original'] ?? null,
            ':payload' => $data['payload'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':expires_at' => $expires,
        ]);

        return $token;
    }
}

/** Fetch a pending email application by token, or null. */
if (!function_exists('cpvia_get_pending_email')) {
    function cpvia_get_pending_email(PDO $pdo, string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM pending_email_applications WHERE token = ?");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

/** Mark a pending record as sent (keeps the row for audit; files cleaned separately). */
if (!function_exists('cpvia_mark_pending_sent')) {
    function cpvia_mark_pending_sent(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare(
            "UPDATE pending_email_applications
             SET status = 'sent', updated_at = ?, last_error = NULL
             WHERE id = ?"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }
}

/** Record a failed send attempt (increments attempts, stores the error). */
if (!function_exists('cpvia_mark_pending_failed')) {
    function cpvia_mark_pending_failed(PDO $pdo, int $id, string $error): void
    {
        $stmt = $pdo->prepare(
            "UPDATE pending_email_applications
             SET status = 'failed', attempts = attempts + 1, last_error = ?, updated_at = ?
             WHERE id = ?"
        );
        $stmt->execute([mb_substr($error, 0, 1000), date('Y-m-d H:i:s'), $id]);
    }
}

/**
 * Clean up an EMAIL_ONLY pending record's temporary attachment files.
 * Only deletes files under uploads/pending/ (never the durable resumes dir).
 */
if (!function_exists('cpvia_cleanup_pending_files')) {
    function cpvia_cleanup_pending_files(string $projectRoot, array $pending): void
    {
        foreach (['resume_path', 'cover_path'] as $k) {
            $rel = (string) ($pending[$k] ?? '');
            if ($rel === '' || strpos($rel, 'uploads/pending/') !== 0) {
                continue; // never touch durable backend files
            }
            $abs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }
}
