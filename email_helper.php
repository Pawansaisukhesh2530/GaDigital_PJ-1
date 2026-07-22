<?php
/**
 * email_helper.php
 * -----------------------------------------------------------------------------
 * Thin wrapper around the vendored PHPMailer (lib/PHPMailer) that:
 *   - loads global SMTP settings from app_settings,
 *   - builds the professional application email from the configurable template,
 *   - sends to one or more recipients with the resume (+ cover letter) attached,
 *   - sanitises all editable content and prevents header injection.
 *
 * No Composer: a tiny autoloader registers the three vendored classes.
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/settings_helpers.php';

if (!function_exists('cpvia_load_phpmailer')) {
    function cpvia_load_phpmailer(): bool
    {
        static $loaded = null;
        if ($loaded !== null) {
            return $loaded;
        }
        $base = __DIR__ . '/lib/PHPMailer/';
        $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
        foreach ($files as $f) {
            if (!is_file($base . $f)) {
                $loaded = false;
                return false;
            }
            require_once $base . $f;
        }
        $loaded = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
        return $loaded;
    }
}

/**
 * Strip any characters that could be used for SMTP header injection from a
 * single-line value (used for the subject).
 */
if (!function_exists('cpvia_sanitize_header_line')) {
    function cpvia_sanitize_header_line(string $value): string
    {
        // Remove CR, LF, NUL and collapse to a single line.
        $value = str_replace(["\r", "\n", "\0"], ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}

/**
 * Build the default subject + body for an application using the configurable
 * templates and the candidate/job data map.
 *
 * @param array<string,string> $data placeholder data (without braces)
 * @return array{subject:string, body:string}
 */
if (!function_exists('cpvia_build_application_email')) {
    function cpvia_build_application_email(PDO $pdo, array $data): array
    {
        $settings = cpvia_get_settings($pdo);
        $subject = cpvia_render_template($settings['email_subject_template'], $data);
        $body    = cpvia_render_template($settings['email_body_template'], $data);

        return [
            'subject' => cpvia_sanitize_header_line($subject),
            'body'    => trim($body),
        ];
    }
}

/**
 * Configure a PHPMailer instance from the stored SMTP settings.
 * Returns [PHPMailer|null, errorMessage].
 */
if (!function_exists('cpvia_make_mailer')) {
    function cpvia_make_mailer(PDO $pdo): array
    {
        if (!cpvia_load_phpmailer()) {
            return [null, 'Email library is not available on the server.'];
        }

        $s = cpvia_get_settings($pdo);
        $missing = cpvia_smtp_missing_fields($s);
        if (!empty($missing)) {
            return [null, 'SMTP is not configured correctly (' . implode('; ', $missing)
                . '). An administrator can fix this in Admin → Settings.'];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $s['smtp_host'];
            $mail->Port = (int) ($s['smtp_port'] !== '' ? $s['smtp_port'] : 587);

            $enc = strtolower(trim($s['smtp_encryption']));
            if ($enc === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            if (trim($s['smtp_username']) !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $s['smtp_username'];
                $mail->Password = $s['smtp_password'];
            } else {
                $mail->SMTPAuth = false;
            }

            $fromEmail = trim($s['smtp_from_email']);
            $fromName  = cpvia_sanitize_header_line($s['smtp_from_name']);
            $mail->setFrom($fromEmail, $fromName !== '' ? $fromName : 'CPVIA Careers');

            $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $mail->Timeout = 20;
        } catch (\Throwable $e) {
            return [null, 'Could not initialise the mailer: ' . $e->getMessage()];
        }

        return [$mail, ''];
    }
}

/**
 * Send an application email.
 *
 * @param string[] $recipients   validated recipient addresses (authoritative)
 * @param string   $subject      editable subject (will be sanitised)
 * @param string   $body         editable plain-text body (will be sanitised)
 * @param array<int,array{path:string,name:string}> $attachments absolute file paths
 * @param string   $replyToEmail candidate email for Reply-To (optional)
 * @param string   $replyToName  candidate name for Reply-To (optional)
 * @param string   $ccSelf       optional candidate address to CC a copy to
 *
 * @return array{ok:bool, error:string}
 */
if (!function_exists('cpvia_send_application_email')) {
    function cpvia_send_application_email(
        PDO $pdo,
        array $recipients,
        string $subject,
        string $body,
        array $attachments = [],
        string $replyToEmail = '',
        string $replyToName = '',
        string $ccSelf = ''
    ): array {
        [$mail, $err] = cpvia_make_mailer($pdo);
        if ($mail === null) {
            return ['ok' => false, 'error' => $err];
        }

        // Recipients are authoritative and must already be validated.
        $valid = [];
        foreach ($recipients as $r) {
            $r = trim((string) $r);
            if (filter_var($r, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $r;
            }
        }
        if (empty($valid)) {
            return ['ok' => false, 'error' => 'No valid recipient email addresses were configured for this job.'];
        }

        try {
            foreach ($valid as $r) {
                $mail->addAddress($r);
            }

            if ($ccSelf !== '' && filter_var($ccSelf, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($ccSelf);
            }

            if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyToEmail, cpvia_sanitize_header_line($replyToName));
            }

            $mail->Subject = cpvia_sanitize_header_line($subject);
            $mail->isHTML(false);
            // Normalise line endings; strip NULs. Content stays plain text.
            $mail->Body = str_replace("\0", '', $body);

            foreach ($attachments as $att) {
                $path = $att['path'] ?? '';
                $name = $att['name'] ?? '';
                if ($path !== '' && is_file($path)) {
                    $mail->addAttachment($path, cpvia_sanitize_header_line((string) $name));
                }
            }

            $mail->send();
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            $msg = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
            return ['ok' => false, 'error' => $msg];
        }
    }
}
