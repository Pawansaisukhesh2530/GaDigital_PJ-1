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
 * @param string   $htmlBody     optional HTML body; when provided the email is
 *                               sent as HTML with $body used as the plain-text
 *                               AltBody. When empty, a plain-text email is sent.
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
        string $ccSelf = '',
        string $htmlBody = ''
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
            if (trim($htmlBody) !== '') {
                // HTML email with a plain-text alternative (AltBody) for clients
                // that cannot render HTML.
                $mail->isHTML(true);
                $mail->Body = str_replace("\0", '', $htmlBody);
                $mail->AltBody = str_replace("\0", '', $body);
            } else {
                // Backward-compatible plain-text path (e.g. the SMTP test email).
                $mail->isHTML(false);
                $mail->Body = str_replace("\0", '', $body);
            }

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

/**
 * Build a modern, responsive HTML application email from structured data.
 * -----------------------------------------------------------------------------
 * Presentation layer only: consumes the structured payload produced by
 * cpvia_apply_email_data() plus the candidate's editable message. All
 * user-provided values are HTML-escaped. Uses inline CSS + a table layout for
 * broad email-client compatibility (Gmail, Outlook, Apple Mail, mobile).
 *
 * @param array<string,mixed> $d              structured data (from payload)
 * @param string              $candidateMessage editable message from the review page
 */
if (!function_exists('cpvia_build_application_email_html')) {
    function cpvia_build_application_email_html(array $d, string $candidateMessage): string
    {
        $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $has = static fn($v) => trim((string) $v) !== '';

        // Palette — light background, dark gray text, minimal brand accent.
        $BRAND   = '#3D1A8A';   // violet, used sparingly (header accent)
        $INK     = '#1F2937';   // primary text
        $BODY    = '#374151';   // secondary text
        $MUTED   = '#6B7280';   // labels
        $BORDER  = '#E5E7EB';   // subtle borders
        $SOFT    = '#F9FAFB';   // soft section background
        $CHIPBG  = '#F3F4F6';

        // ---- helpers to render field rows / sections ----------------------
        $rows = static function (array $pairs) use ($esc, $has, $MUTED, $INK, $BORDER): string {
            $out = '';
            foreach ($pairs as $label => $value) {
                if (!$has($value)) { continue; }
                $out .= '<tr>'
                    . '<td style="padding:6px 0;vertical-align:top;width:44%;color:' . $MUTED . ';font-size:13px;">' . $esc($label) . '</td>'
                    . '<td style="padding:6px 0;vertical-align:top;color:' . $INK . ';font-size:13px;font-weight:600;">' . $esc($value) . '</td>'
                    . '</tr>';
            }
            return $out;
        };
        $section = static function (string $title, string $inner) use ($BRAND, $BORDER): string {
            if (trim($inner) === '') { return ''; }
            return '<tr><td style="padding:22px 28px 0 28px;">'
                . '<div style="font-size:12px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:' . $BRAND . ';border-bottom:1px solid ' . $BORDER . ';padding-bottom:8px;margin-bottom:10px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
                . $inner
                . '</td></tr>';
        };
        $fieldTable = static fn(string $r) => $r === '' ? '' : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $r . '</table>';

        // ---- Application Summary ------------------------------------------
        $summaryRows = $rows([
            'Application ID'  => $d['application_id'] ?? '',
            'Submission Date' => $d['submission_date'] ?? '',
            'Job Title'       => $d['job_title'] ?? '',
        ]);

        // ---- Candidate Information ----------------------------------------
        $candRows = $rows([
            'Name'     => $d['candidate_name'] ?? '',
            'Email'    => $d['candidate_email'] ?? '',
            'Phone'    => $d['candidate_phone'] ?? '',
            'Location' => $d['candidate_location'] ?? '',
        ]);

        // ---- Professional Information -------------------------------------
        $profRows = $rows([
            'Current Company'     => $d['current_company'] ?? '',
            'Current Designation' => $d['current_designation'] ?? '',
            'Total Experience'    => $d['total_experience'] ?? '',
            'Notice Period'       => $d['notice_period'] ?? '',
        ]);

        // ---- Education -----------------------------------------------------
        $degree = trim((string) ($d['qualification'] ?? ''));
        if ($has($d['specialization'] ?? '')) {
            $degree = trim($degree . ' (' . $d['specialization'] . ')');
        }
        $eduRows = $rows([
            'Degree'          => $degree,
            'Institution'     => $d['university_college'] ?? '',
            'Graduation Year' => $d['graduation_year'] ?? '',
        ]);

        // ---- Technical Skills (badges) ------------------------------------
        $skillsInner = '';
        $skills = is_array($d['skills'] ?? null) ? $d['skills'] : [];
        if (!empty($skills)) {
            $chips = '';
            foreach ($skills as $s) {
                if (!$has($s)) { continue; }
                $chips .= '<span style="display:inline-block;background:' . $CHIPBG . ';border:1px solid ' . $BORDER . ';color:' . $BODY . ';font-size:12px;font-weight:600;padding:5px 11px;border-radius:14px;margin:0 6px 6px 0;">' . $esc($s) . '</span>';
            }
            $skillsInner = '<div style="line-height:1.9;">' . $chips . '</div>';
        }

        // ---- Languages (badges) -------------------------------------------
        $langInner = '';
        $languages = is_array($d['languages'] ?? null) ? $d['languages'] : [];
        if (!empty($languages)) {
            $chips = '';
            foreach ($languages as $l) {
                if (!$has($l)) { continue; }
                $chips .= '<span style="display:inline-block;background:' . $CHIPBG . ';border:1px solid ' . $BORDER . ';color:' . $BODY . ';font-size:12px;font-weight:600;padding:5px 11px;border-radius:14px;margin:0 6px 6px 0;">' . $esc($l) . '</span>';
            }
            $langInner = '<div style="line-height:1.9;">' . $chips . '</div>';
        }

        // ---- Portfolio Links ----------------------------------------------
        $linkLine = static function (string $label, string $url) use ($esc, $has, $MUTED, $BRAND): string {
            if (!$has($url)) { return ''; }
            $safe = $esc($url);
            return '<tr>'
                . '<td style="padding:6px 0;width:44%;color:' . $MUTED . ';font-size:13px;">' . $esc($label) . '</td>'
                . '<td style="padding:6px 0;font-size:13px;"><a href="' . $safe . '" style="color:' . $BRAND . ';text-decoration:underline;word-break:break-all;">' . $safe . '</a></td>'
                . '</tr>';
        };
        $linkRows = $linkLine('LinkedIn', (string) ($d['linkedin'] ?? ''))
            . $linkLine('GitHub', (string) ($d['github'] ?? ''))
            . $linkLine('Portfolio', (string) ($d['portfolio'] ?? ''));

        // ---- Candidate Message (editable content) -------------------------
        $msgInner = '';
        if ($has($candidateMessage)) {
            $msgInner = '<div style="color:' . $BODY . ';font-size:14px;line-height:1.65;white-space:pre-line;">'
                . nl2br($esc($candidateMessage)) . '</div>';
        }

        // ---- Attachments ---------------------------------------------------
        $attInner = '';
        $attItems = '';
        foreach ([['Resume', $d['resume_name'] ?? ''], ['Cover Letter', $d['cover_name'] ?? '']] as $a) {
            if (!$has($a[1])) { continue; }
            $attItems .= '<tr>'
                . '<td style="padding:8px 10px;border:1px solid ' . $BORDER . ';border-radius:6px;font-size:13px;color:' . $INK . ';background:' . $SOFT . ';">'
                . '&#128206; <strong style="color:' . $MUTED . ';font-weight:600;">' . $esc($a[0]) . ':</strong> ' . $esc($a[1])
                . '</td></tr><tr><td style="height:8px;line-height:8px;">&nbsp;</td></tr>';
        }
        if ($attItems !== '') {
            $attInner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;">' . $attItems . '</table>';
        }

        $company = $esc($d['company_name'] ?? 'CPVIA');
        $year = date('Y');
        $preheader = 'Application for ' . $esc($d['job_title'] ?? '') . ' — ' . $esc($d['candidate_name'] ?? '');

        // ---- Assemble ------------------------------------------------------
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<meta http-equiv="X-UA-Compatible" content="IE=edge">'
            . '<title>Job Application</title>'
            . '<style>'
            . 'body{margin:0;padding:0;background:#F3F4F6;}'
            . '@media only screen and (max-width:620px){'
            . '.cp-container{width:100%!important;}'
            . '.cp-pad{padding-left:18px!important;padding-right:18px!important;}'
            . '}'
            . '</style></head>'
            . '<body style="margin:0;padding:0;background:#F3F4F6;">'
            . '<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">' . $preheader . '</span>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F6;padding:24px 0;">'
            . '<tr><td align="center">'
            . '<table role="presentation" class="cp-container" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#FFFFFF;border:1px solid ' . $BORDER . ';border-radius:10px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">'

            // Header
            . '<tr><td style="height:4px;line-height:4px;background:' . $BRAND . ';">&nbsp;</td></tr>'
            . '<tr><td class="cp-pad" style="padding:24px 28px 18px 28px;border-bottom:1px solid ' . $BORDER . ';">'
            . '<div style="font-size:20px;font-weight:800;color:' . $INK . ';letter-spacing:.5px;">' . $company . '</div>'
            . '<div style="font-size:12px;color:' . $MUTED . ';margin-top:2px;">Recruitment Management System</div>'
            . '</td></tr>'

            // Title band
            . '<tr><td class="cp-pad" style="padding:18px 28px 0 28px;">'
            . '<div style="font-size:16px;font-weight:700;color:' . $INK . ';">New Job Application</div>'
            . '<div style="font-size:13px;color:' . $MUTED . ';margin-top:2px;">' . $esc($d['job_title'] ?? '') . '</div>'
            . '</td></tr>';

        // Sections (each wraps its own padding via $section)
        $html .= str_replace('padding:22px 28px 0 28px', 'padding:20px 28px 0 28px', $section('Application Summary', $fieldTable($summaryRows)));
        $html .= $section('Candidate Information', $fieldTable($candRows));
        $html .= $section('Professional Information', $fieldTable($profRows));
        $html .= $section('Education', $fieldTable($eduRows));
        $html .= $section('Technical Skills', $skillsInner);
        $html .= $section('Languages', $langInner);
        $html .= $section('Portfolio Links', $fieldTable($linkRows));
        $html .= $section('Candidate Message', $msgInner);
        $html .= $section('Attachments', $attInner);

        // Footer
        $html .= '<tr><td style="padding:26px 28px 22px 28px;"></td></tr>'
            . '<tr><td class="cp-pad" style="padding:16px 28px;background:' . $SOFT . ';border-top:1px solid ' . $BORDER . ';">'
            . '<div style="font-size:11px;color:' . $MUTED . ';line-height:1.6;">'
            . 'This email was generated automatically by the ' . $company . ' Recruitment Management System.<br>'
            . '&copy; ' . $year . ' ' . $company . '. Please do not reply to the system; use Reply to contact the candidate.'
            . '</div></td></tr>'

            . '</table></td></tr></table></body></html>';

        return $html;
    }
}