<?php
/**
 * settings_helpers.php
 * -----------------------------------------------------------------------------
 * Global application settings (key/value) used by the Application Delivery
 * feature: SMTP configuration and the configurable email template.
 *
 * Storage: a single `app_settings` table (key TEXT PRIMARY KEY, value TEXT).
 * All helpers are self-contained and reuse the shared cpvia_db() connection.
 *
 * NOTE: SMTP settings are GLOBAL (not per-job). Per-job delivery behaviour
 * lives on the jobs table (submission_mode + recipient_emails).
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';

if (!function_exists('cpvia_ensure_settings_table')) {
    function cpvia_ensure_settings_table(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

/**
 * Default values for every recognised setting key. Also acts as the whitelist
 * of keys the Settings page is allowed to write.
 *
 * @return array<string,string>
 */
if (!function_exists('cpvia_settings_defaults')) {
    function cpvia_settings_defaults(): array
    {
        return [
            // --- SMTP (global) ---
            'smtp_host'        => '',
            'smtp_port'        => '587',
            'smtp_username'    => '',
            'smtp_password'    => '',
            'smtp_encryption'  => 'tls',   // none | ssl | tls
            'smtp_from_name'   => 'CPVIA Careers',
            'smtp_from_email'  => '',

            // --- Branding used inside email templates ---
            'company_name'     => 'CPVIA',

            // --- Configurable email template (placeholders documented below) ---
            'email_subject_template' => 'Application for {job_title} - {candidate_name}',
            'email_body_template'    => cpvia_default_email_body_template(),
        ];
    }
}

/**
 * The default professional application-summary body template. The
 * {application_summary} placeholder is expanded automatically into a
 * fully-formatted, sectioned candidate summary at send time.
 */
if (!function_exists('cpvia_default_email_body_template')) {
    function cpvia_default_email_body_template(): string
    {
        return "Dear Hiring Team,\n\n"
            . "Please find below a professional summary of the application submitted for the "
            . "{job_title} position at {company_name}. The candidate's full resume is attached "
            . "for your detailed review.\n\n"
            . "{application_summary}\n\n"
            . "Kind regards,\n"
            . "{candidate_name}\n"
            . "{candidate_email}\n"
            . "{candidate_phone}";
    }
}

/** The list of template placeholders shown to admins on the Settings page. */
if (!function_exists('cpvia_email_placeholders')) {
    function cpvia_email_placeholders(): array
    {
        return [
            '{candidate_name}'        => "Applicant's full name",
            '{candidate_email}'       => "Applicant's email address",
            '{candidate_phone}'       => "Applicant's phone number",
            '{candidate_location}'    => "Applicant's current location",
            '{job_title}'             => 'Job title being applied for',
            '{job_code}'              => 'Internal job code (if set)',
            '{department}'            => 'Department of the job',
            '{company_name}'          => 'Your organisation name',
            '{total_experience}'      => 'Total years of experience',
            '{current_company}'       => "Applicant's current company",
            '{current_designation}'   => "Applicant's current designation",
            '{application_id}'        => 'Application reference ID',
            '{submission_date}'       => 'Date the application was submitted',
            '{application_summary}'   => 'Full auto-generated professional summary (sections, skills, education, links, cover letter, attachments)',
        ];
    }
}

/**
 * Load all settings merged over the defaults, so callers always get a complete
 * set even before the Settings page has ever been saved.
 *
 * @return array<string,string>
 */
if (!function_exists('cpvia_get_settings')) {
    function cpvia_get_settings(PDO $pdo): array
    {
        $defaults = cpvia_settings_defaults();
        try {
            cpvia_ensure_settings_table($pdo);
            $rows = $pdo->query("SELECT key, value FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) {
            return $defaults;
        }
        $out = $defaults;
        foreach ($rows as $k => $v) {
            if (array_key_exists($k, $defaults)) {
                $out[$k] = (string) $v;
            }
        }
        return $out;
    }
}

/** Fetch a single setting value (falls back to its default). */
if (!function_exists('cpvia_get_setting')) {
    function cpvia_get_setting(PDO $pdo, string $key, ?string $fallback = null): string
    {
        $all = cpvia_get_settings($pdo);
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return $fallback ?? '';
    }
}

/**
 * Persist a set of settings. Only whitelisted keys (present in the defaults)
 * are written. Values are stored as-is (strings).
 *
 * @param array<string,string> $values
 */
if (!function_exists('cpvia_save_settings')) {
    function cpvia_save_settings(PDO $pdo, array $values): void
    {
        cpvia_ensure_settings_table($pdo);
        $allowed = cpvia_settings_defaults();

        $stmt = $pdo->prepare(
            "INSERT INTO app_settings (key, value, updated_at)
             VALUES (:key, :value, :updated_at)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at"
        );

        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            foreach ($values as $k => $v) {
                if (!array_key_exists($k, $allowed)) {
                    continue;
                }
                $stmt->execute([':key' => $k, ':value' => (string) $v, ':updated_at' => $now]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

/**
 * Inspect the stored SMTP settings and return a list of human-readable
 * messages for each required field that is missing or inconsistent.
 * An empty array means the SMTP configuration is complete enough to attempt
 * a connection.
 *
 * Rules:
 *   - SMTP Host is always required.
 *   - From Email is always required and must be a valid address.
 *   - If a Username is set, a Password must also be set (and vice versa),
 *     because authenticated SMTP needs both.
 *
 * @param array<string,string> $s a settings map (as returned by cpvia_get_settings)
 * @return string[] list of missing/invalid field messages
 */
if (!function_exists('cpvia_smtp_missing_fields')) {
    function cpvia_smtp_missing_fields(array $s): array
    {
        $missing = [];

        $host = trim((string) ($s['smtp_host'] ?? ''));
        if ($host === '') {
            $missing[] = 'Missing SMTP Host';
        }

        $fromEmail = trim((string) ($s['smtp_from_email'] ?? ''));
        if ($fromEmail === '') {
            $missing[] = 'Missing From Email';
        } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'From Email is not a valid email address';
        }

        $port = trim((string) ($s['smtp_port'] ?? ''));
        if ($port === '' || (int) $port <= 0) {
            $missing[] = 'Missing or invalid SMTP Port';
        }

        $user = trim((string) ($s['smtp_username'] ?? ''));
        $pass = (string) ($s['smtp_password'] ?? '');
        if ($user !== '' && $pass === '') {
            $missing[] = 'Missing SMTP Password (a username is set, so a password is required)';
        }
        if ($user === '' && $pass !== '') {
            $missing[] = 'Missing SMTP Username (a password is set, so a username is required)';
        }

        return $missing;
    }
}

/**
 * Validate + normalise a comma-separated list of recipient email addresses.
 * Rejects header-injection attempts (CR/LF) and invalid addresses.
 *
 * @return array{ok:bool, emails:string[], error:string, normalized:string}
 */
if (!function_exists('cpvia_parse_email_list')) {
    function cpvia_parse_email_list(?string $raw, int $max = 20): array
    {
        $raw = (string) $raw;

        // Any control / newline character is an injection attempt.
        if (preg_match('/[\r\n\t\0]/', $raw)) {
            return ['ok' => false, 'emails' => [], 'error' => 'Email addresses contain invalid characters.', 'normalized' => ''];
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn($v) => $v !== '');
        $emails = [];
        foreach ($parts as $p) {
            if (!filter_var($p, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'emails' => [], 'error' => 'One or more email addresses are invalid: ' . $p, 'normalized' => ''];
            }
            $lower = strtolower($p);
            if (!in_array($lower, array_map('strtolower', $emails), true)) {
                $emails[] = $p;
            }
            if (count($emails) > $max) {
                return ['ok' => false, 'emails' => [], 'error' => 'Too many recipient addresses (max ' . $max . ').', 'normalized' => ''];
            }
        }

        return ['ok' => true, 'emails' => $emails, 'error' => '', 'normalized' => implode(', ', $emails)];
    }
}

/**
 * Render a template string by replacing {placeholder} tokens with values from
 * the provided data map. Unknown placeholders are left blank.
 *
 * @param array<string,string> $data placeholder => value (without braces)
 */
if (!function_exists('cpvia_render_template')) {
    function cpvia_render_template(string $template, array $data): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', static function ($m) use ($data) {
            $key = $m[1];
            return array_key_exists($key, $data) ? (string) $data[$key] : '';
        }, $template);
    }
}
