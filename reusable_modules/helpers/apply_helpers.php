<?php
/**
 * apply_helpers.php
 * -----------------------------------------------------------------------------
 * Reusable, self-contained helpers for the public 7-step Candidate Application
 * Wizard. No hardcoded absolute paths — callers pass the PDO connection and
 * directories. Designed to be portable into another CPVIA project folder.
 * -----------------------------------------------------------------------------
 */

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

        $clean['linkedin_profile'] = $str('linkedin_profile', 255);
        if ($clean['linkedin_profile'] !== '') {
            if (!filter_var($clean['linkedin_profile'], FILTER_VALIDATE_URL)
                || !preg_match('#^https?://#i', $clean['linkedin_profile'])) {
                $errors['linkedin_profile'] = 'Enter a valid URL starting with http(s):// or leave it blank.';
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
            if (!filter_var($clean['portfolio_url'], FILTER_VALIDATE_URL)
                || !preg_match('#^https?://#i', $clean['portfolio_url'])) {
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
