<?php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../settings_helpers.php';

if (!function_exists('cpvia_job_status')) {
    function cpvia_job_status(string $key): string
    {
        $map = [
            'draft'     => 'Draft',
            'published' => 'Active',
        ];
        return $map[$key] ?? 'Draft';
    }
}

/**
 * Fetch all active master skills (id + name), ordered alphabetically.
 * @return array<int, array{id:int, name:string}>
 */
if (!function_exists('cpvia_fetch_skills')) {
    function cpvia_fetch_skills(PDO $pdo): array
    {
        try {
            $rows = $pdo->query("SELECT id, name FROM skills WHERE is_active = 1 ORDER BY name COLLATE NOCASE ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
        return array_map(static function ($r) {
            return ['id' => (int) $r['id'], 'name' => (string) $r['name']];
        }, $rows);
    }
}

/**
 * Sanitize rich-text HTML coming from the wizard editor.
 * Allows a small, safe subset of formatting tags and cleans links.
 */
if (!function_exists('cpvia_sanitize_richtext')) {
    function cpvia_sanitize_richtext(?string $html): string
    {
        $html = (string) $html;
        if (trim(strip_tags($html)) === '' && strip_tags($html, '<br>') === $html) {
            // fall through; still sanitize below
        }
        if (trim($html) === '') {
            return '';
        }

        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a>';
        $clean = strip_tags($html, $allowed);

        // Strip any inline event handlers (onclick=, onerror=, ...).
        $clean = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $clean);
        $clean = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $clean);

        // Keep only a safe href on <a>, force safe rel/target, drop everything else.
        $clean = preg_replace_callback('/<a\b[^>]*>/i', static function ($m) {
            $tag = $m[0];
            $url = '';
            if (preg_match('/href\s*=\s*"([^"]*)"/i', $tag, $h) || preg_match("/href\s*=\s*'([^']*)'/i", $tag, $h)) {
                $url = trim($h[1]);
            }
            if ($url === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $url)) {
                return '<a>';
            }
            $safe = htmlspecialchars($url, ENT_QUOTES);
            return '<a href="' . $safe . '" target="_blank" rel="noopener nofollow">';
        }, $clean);

        return trim($clean);
    }
}

/**
 * Replace all skills for a job of a given type, then insert the provided set.
 * Prevents duplicates via INSERT OR IGNORE + the UNIQUE(job_id,skill_id,skill_type)
 * constraint. Only skill IDs that exist in the master table are stored.
 *
 * @param int[] $skillIds
 * @param string $type 'required' | 'preferred'
 */
if (!function_exists('cpvia_replace_job_skills')) {
    function cpvia_replace_job_skills(PDO $pdo, int $jobId, array $skillIds, string $type): void
    {
        $type = in_array($type, ['required', 'preferred'], true) ? $type : 'required';

        // Remove existing rows of this type (supports re-use by Edit Job later).
        $del = $pdo->prepare("DELETE FROM job_skills WHERE job_id = ? AND skill_type = ?");
        $del->execute([$jobId, $type]);

        if (empty($skillIds)) {
            return;
        }

        // Only keep IDs that actually exist in the master skills table.
        $valid = [];
        $check = $pdo->prepare("SELECT COUNT(*) FROM skills WHERE id = ?");
        foreach (array_unique(array_map('intval', $skillIds)) as $sid) {
            if ($sid <= 0) {
                continue;
            }
            $check->execute([$sid]);
            if ((int) $check->fetchColumn() > 0) {
                $valid[] = $sid;
            }
        }

        if (empty($valid)) {
            return;
        }

        $ins = $pdo->prepare("INSERT OR IGNORE INTO job_skills (job_id, skill_id, skill_type) VALUES (?, ?, ?)");
        foreach ($valid as $sid) {
            $ins->execute([$jobId, $sid, $type]);
        }
    }
}

/**
 * Parse a comma-separated list of skill IDs from a POST field into an int array.
 */
if (!function_exists('cpvia_parse_skill_ids')) {
    function cpvia_parse_skill_ids(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn($v) => $v !== '');
        return array_values(array_unique(array_map('intval', $parts)));
    }
}

/**
 * Compose the legacy single-line `location` column from granular parts so the
 * existing Careers page and Jobs list keep displaying a meaningful location.
 */
if (!function_exists('cpvia_compose_location')) {
    function cpvia_compose_location(string $city, string $state, string $country, string $office): string
    {
        $parts = array_filter([trim($city), trim($state), trim($country)], static fn($v) => $v !== '');
        $loc = implode(', ', $parts);
        if ($loc === '') {
            $loc = trim($office);
        }
        return $loc;
    }
}

/* ============================================================================
 * SHARED WIZARD LOGIC (used by BOTH Add Job and Edit Job)
 * ========================================================================== */

/** Option lists for the wizard dropdowns. Single source of truth. */
if (!function_exists('cpvia_job_option_lists')) {
    function cpvia_job_option_lists(): array
    {
        return [
            'employment_types' => ['Full-Time', 'Part-Time', 'Contract', 'Internship', 'Temporary', 'Freelance'],
            'work_modes'       => ['On-site', 'Hybrid', 'Remote'],
            'priorities'       => ['Normal', 'High', 'Urgent'],
            'salary_types'     => ['Annual', 'Monthly', 'Hourly'],
            'currencies'       => ['INR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED'],
            'qualifications'   => ['High School', 'Diploma', "Bachelor's", "Master's", 'Doctorate', 'Other'],
            'genders'          => ['Any', 'Male', 'Female'],
        ];
    }
}

/** Default job field values (also used to repopulate on validation failure). */
if (!function_exists('cpvia_default_job_values')) {
    function cpvia_default_job_values(): array
    {
        return [
            'title' => '', 'department' => '', 'job_code' => '',
            'employment_type' => 'Full-Time', 'work_mode' => 'On-site',
            'number_of_openings' => '1', 'hiring_priority' => 'Normal',
            'country' => '', 'state' => '', 'city' => '', 'office_location' => '', 'remote_available' => 0,
            'min_experience' => '', 'max_experience' => '',
            'minimum_qualification' => '', 'degree' => '', 'specialization' => '',
            'salary_type' => 'Annual', 'min_salary' => '', 'max_salary' => '', 'currency' => 'INR',
            'description' => '', 'responsibilities' => '', 'requirements' => '', 'benefits' => '',
            'preferred_notice_period' => '', 'gender_preference' => 'Any',
            'minimum_age' => '', 'maximum_age' => '',
            // Application Delivery (per-job)
            'submission_mode' => 'BACKEND_ONLY', 'recipient_emails' => '',
        ];
    }
}

/** Allowed submission modes for the Application Delivery section. */
if (!function_exists('cpvia_submission_modes')) {
    function cpvia_submission_modes(): array
    {
        return ['BACKEND_ONLY', 'EMAIL_ONLY', 'BACKEND_AND_EMAIL'];
    }
}

/** True when the given submission mode requires at least one recipient email. */
if (!function_exists('cpvia_mode_needs_email')) {
    function cpvia_mode_needs_email(string $mode): bool
    {
        return in_array($mode, ['EMAIL_ONLY', 'BACKEND_AND_EMAIL'], true);
    }
}

/** Rich-text considered empty when it has no visible text content. */
if (!function_exists('cpvia_rich_is_empty')) {
    function cpvia_rich_is_empty(string $html): bool
    {
        $t = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '&nbsp;'], ' ', $html)));
        return $t === '';
    }
}

/**
 * Collect + sanitize wizard POST data.
 * @return array{values: array, required: int[], preferred: int[]}
 */
if (!function_exists('cpvia_collect_job_post')) {
    function cpvia_collect_job_post(array $post): array
    {
        $values = cpvia_default_job_values();
        $rich = ['description', 'responsibilities', 'requirements', 'benefits'];

        foreach (array_keys($values) as $k) {
            if ($k === 'remote_available') {
                continue;
            }
            if (in_array($k, $rich, true)) {
                $values[$k] = cpvia_sanitize_richtext($post[$k] ?? '');
            } else {
                $values[$k] = trim((string) ($post[$k] ?? ''));
            }
        }
        $values['remote_available'] = isset($post['remote_available']) ? 1 : 0;
        if ($values['number_of_openings'] === '') {
            $values['number_of_openings'] = '1';
        }
        if ($values['gender_preference'] === '') {
            $values['gender_preference'] = 'Any';
        }

        // Application Delivery: normalise the mode to a known value.
        if (!in_array($values['submission_mode'], cpvia_submission_modes(), true)) {
            $values['submission_mode'] = 'BACKEND_ONLY';
        }
        // Recipient emails only matter when the mode uses email.
        if (!cpvia_mode_needs_email($values['submission_mode'])) {
            $values['recipient_emails'] = '';
        }

        return [
            'values'    => $values,
            'required'  => cpvia_parse_skill_ids($post['required_skills'] ?? ''),
            'preferred' => cpvia_parse_skill_ids($post['preferred_skills'] ?? ''),
        ];
    }
}

/**
 * Server-side validation shared by Add and Edit.
 * @param string $action 'draft' | 'publish'
 * @param int|null $excludeId job id to exclude from job_code uniqueness (Edit)
 * @return string empty string when valid, otherwise the error message
 */
if (!function_exists('cpvia_validate_job')) {
    function cpvia_validate_job(PDO $pdo, array $values, string $action, ?int $excludeId = null): string
    {
        if ($values['title'] === '') {
            return 'A job title is required, even to save a draft.';
        }

        if ($action === 'publish' && (
            $values['department'] === '' ||
            $values['employment_type'] === '' ||
            $values['city'] === '' ||
            cpvia_rich_is_empty($values['description']) ||
            cpvia_rich_is_empty($values['requirements'])
        )) {
            return 'Please complete all required fields (Department, Employment Type, City, Description, Requirements) before publishing.';
        }

        $num = static fn($v) => ($v !== '' && is_numeric($v)) ? (float) $v : null;

        $minE = $num($values['min_experience']);
        $maxE = $num($values['max_experience']);
        if ($minE !== null && $maxE !== null && $maxE < $minE) {
            return 'Maximum experience cannot be lower than minimum experience.';
        }

        $minS = $num($values['min_salary']);
        $maxS = $num($values['max_salary']);
        if ($minS !== null && $maxS !== null && $maxS < $minS) {
            return 'Maximum salary cannot be lower than minimum salary.';
        }

        $minA = $num($values['minimum_age']);
        $maxA = $num($values['maximum_age']);
        if ($minA !== null && $maxA !== null && $maxA < $minA) {
            return 'Maximum age cannot be lower than minimum age.';
        }

        // Application Delivery validation.
        if (!in_array($values['submission_mode'], cpvia_submission_modes(), true)) {
            return 'Please choose a valid Application Delivery option.';
        }
        if (cpvia_mode_needs_email($values['submission_mode'])) {
            $raw = trim((string) ($values['recipient_emails'] ?? ''));
            // Always reject malformed input; only require presence when publishing.
            if ($raw !== '') {
                $parsed = cpvia_parse_email_list($raw);
                if (!$parsed['ok']) {
                    return $parsed['error'];
                }
            } elseif ($action === 'publish') {
                return 'Please provide at least one recipient email for the selected delivery option.';
            }
        }

        // Job Code uniqueness (only when provided). Excludes the current job on Edit.
        if ($values['job_code'] !== '') {
            $sql = "SELECT COUNT(*) FROM jobs WHERE job_code = ?";
            $params = [$values['job_code']];
            if ($excludeId !== null) {
                $sql .= " AND id <> ?";
                $params[] = $excludeId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() > 0) {
                return 'That Job Code is already used by another job. Please choose a unique code.';
            }
        }

        return '';
    }
}

/**
 * Map a jobs table row into the wizard $values array, handling legacy jobs
 * gracefully (e.g. only the old single-line `location` column populated).
 */
if (!function_exists('cpvia_job_row_to_values')) {
    function cpvia_job_row_to_values(array $job): array
    {
        $values = cpvia_default_job_values();
        foreach (array_keys($values) as $k) {
            if ($k === 'remote_available') {
                $values[$k] = (int) ($job['remote_available'] ?? 0);
                continue;
            }
            if (array_key_exists($k, $job) && $job[$k] !== null) {
                $values[$k] = (string) $job[$k];
            }
        }

        // Legacy fallback: if granular location fields are empty but the old
        // single-line `location` has data, surface it in City so it stays
        // visible and is not silently overwritten on save.
        $granularEmpty = ($values['country'] === '' && $values['state'] === '' && $values['city'] === '' && $values['office_location'] === '');
        if ($granularEmpty && !empty($job['location'])) {
            $values['city'] = (string) $job['location'];
        }

        return $values;
    }
}

/**
 * Bind the shared job field set as an associative param array for INSERT/UPDATE.
 * Numeric/optional fields become NULL when empty. `location` and `status` and
 * `updated_at` are supplied by the caller.
 */
if (!function_exists('cpvia_job_param_map')) {
    function cpvia_job_param_map(array $values, string $location, string $status, string $updatedAt): array
    {
        $numOrNull = static fn($v) => ($v === '' || $v === null) ? null : $v;

        return [
            ':title' => $values['title'],
            ':department' => $values['department'],
            ':location' => $location,
            ':employment_type' => $values['employment_type'],
            ':description' => $values['description'],
            ':requirements' => $values['requirements'],
            ':status' => $status,
            ':job_code' => $numOrNull($values['job_code']),
            ':work_mode' => $numOrNull($values['work_mode']),
            ':number_of_openings' => (int) ($values['number_of_openings'] === '' ? 1 : $values['number_of_openings']),
            ':hiring_priority' => $numOrNull($values['hiring_priority']),
            ':country' => $numOrNull($values['country']),
            ':state' => $numOrNull($values['state']),
            ':city' => $numOrNull($values['city']),
            ':office_location' => $numOrNull($values['office_location']),
            ':remote_available' => (int) $values['remote_available'],
            ':min_experience' => $numOrNull($values['min_experience']),
            ':max_experience' => $numOrNull($values['max_experience']),
            ':minimum_qualification' => $numOrNull($values['minimum_qualification']),
            ':degree' => $numOrNull($values['degree']),
            ':specialization' => $numOrNull($values['specialization']),
            ':salary_type' => $numOrNull($values['salary_type']),
            ':min_salary' => $numOrNull($values['min_salary']),
            ':max_salary' => $numOrNull($values['max_salary']),
            ':currency' => $numOrNull($values['currency']),
            ':responsibilities' => $values['responsibilities'],
            ':benefits' => $values['benefits'],
            ':preferred_notice_period' => $numOrNull($values['preferred_notice_period']),
            ':gender_preference' => $values['gender_preference'] === '' ? 'Any' : $values['gender_preference'],
            ':minimum_age' => $numOrNull($values['minimum_age']),
            ':maximum_age' => $numOrNull($values['maximum_age']),
            ':updated_at' => $updatedAt,
            ':submission_mode' => in_array($values['submission_mode'] ?? '', cpvia_submission_modes(), true)
                ? $values['submission_mode'] : 'BACKEND_ONLY',
            ':recipient_emails' => (function () use ($values) {
                if (!cpvia_mode_needs_email($values['submission_mode'] ?? 'BACKEND_ONLY')) {
                    return null;
                }
                $parsed = cpvia_parse_email_list($values['recipient_emails'] ?? '');
                return $parsed['ok'] && $parsed['normalized'] !== '' ? $parsed['normalized'] : null;
            })(),
        ];
    }
}
