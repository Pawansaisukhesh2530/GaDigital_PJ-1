<?php
/**
 * review_email.php — Candidate Email Review & Send
 * -----------------------------------------------------------------------------
 * Shown for jobs whose Application Delivery mode sends applications by email
 * (EMAIL_ONLY or BACKEND_AND_EMAIL). The candidate reviews the auto-generated
 * email, may edit ONLY the subject and message, then sends it.
 *
 * SECURITY:
 *   - Recipients, attachments, job_id and application_id are read ONLY from the
 *     durable pending_email_applications record (server-side, keyed by token).
 *     They are never accepted from the client, so they cannot be tampered with.
 *   - Editable content (subject/body) is sanitised before sending; the subject
 *     is stripped of CR/LF to prevent header injection.
 *   - CSRF token protects the send action.
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/apply_helpers.php';
require_once __DIR__ . '/email_helper.php';

$db_file = __DIR__ . '/admin/cpvia_database.sqlite';

if (session_status() === PHP_SESSION_NONE) {
    $sess_dir = __DIR__ . '/sessions';
    if (!is_dir($sess_dir)) {
        @mkdir($sess_dir, 0700, true);
    }
    if (is_dir($sess_dir) && is_writable($sess_dir)) {
        session_save_path($sess_dir);
    }
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    @session_start();
}
if (empty($_SESSION['review_csrf'])) {
    $_SESSION['review_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['review_csrf'];

$pdo = cpvia_db($db_file);

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$pending = cpvia_get_pending_email($pdo, $token);

$state = 'ok';        // ok | invalid | expired | already_sent | success
$error = '';
$subject = '';
$body = '';
$recipients = [];
$attachments_meta = [];

if (!$pending) {
    $state = 'invalid';
} else {
    $mode = cpvia_apply_normalize_mode($pending['mode']);

    // Already completed?
    if (($pending['status'] ?? '') === 'sent') {
        $state = 'already_sent';
    } elseif (!empty($pending['expires_at']) && strtotime($pending['expires_at']) < time()) {
        // Expired — clean up any temporary EMAIL_ONLY files.
        cpvia_cleanup_pending_files(__DIR__, $pending);
        $state = 'expired';
    }

    if ($state === 'ok') {
        $parsed = cpvia_parse_email_list($pending['recipient_emails'] ?? '');
        $recipients = $parsed['emails'];

        // Read-only header roles (informational display only — the actual
        // From/Reply-To are set server-side at send time and cannot be changed
        // by the candidate).
        $settings = cpvia_get_settings($pdo);
        $from_display = trim($settings['smtp_from_name'] . ' <' . $settings['smtp_from_email'] . '>');
        if (trim($settings['smtp_from_email']) === '') {
            $from_display = '(configured by the employer)';
        }
        $reply_to_name = (string) ($pending['candidate_name'] ?? '');
        $reply_to_email = (string) ($pending['candidate_email'] ?? '');
        $reply_to_display = $reply_to_email !== ''
            ? trim(($reply_to_name !== '' ? $reply_to_name . ' ' : '') . '<' . $reply_to_email . '>')
            : '';

        // Authoritative attachments derived from the server-side record.
        foreach ([['resume_path', 'resume_original', 'Resume'], ['cover_path', 'cover_original', 'Cover Letter']] as $a) {
            $rel = (string) ($pending[$a[0]] ?? '');
            if ($rel === '') {
                continue;
            }
            $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $orig = (string) ($pending[$a[1]] ?? basename($rel));
            $attachments_meta[] = [
                'label' => $a[2],
                'name'  => $orig,
                'path'  => $abs,
                'exists' => is_file($abs),
            ];
        }

        // Default editable content (from the pending record).
        $subject = (string) ($pending['subject'] ?? '');
        $body = (string) ($pending['body'] ?? '');

        // ---- Handle Send ----
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'send') {
            // Repopulate editable fields from the candidate's input.
            $subject = (string) ($_POST['subject'] ?? $subject);
            $body = (string) ($_POST['body'] ?? $body);
            $send_copy = !empty($_POST['send_copy']);

            $csrf_ok = isset($_POST['csrf_token']) && hash_equals($csrf, (string) $_POST['csrf_token']);

            if (!$csrf_ok) {
                $error = 'Your session expired. Please review your email and click Send again.';
            } elseif (trim($subject) === '') {
                $error = 'The subject cannot be empty.';
            } elseif (trim($body) === '') {
                $error = 'The message cannot be empty.';
            } elseif (empty($recipients)) {
                $error = 'No valid recipient is configured for this job. Please contact us.';
            } else {
                // Build attachments (only existing files).
                $attachments = [];
                foreach ($attachments_meta as $m) {
                    if ($m['exists']) {
                        $attachments[] = ['path' => $m['path'], 'name' => $m['name']];
                    }
                }

                $cc = $send_copy ? (string) ($pending['candidate_email'] ?? '') : '';

                $result = cpvia_send_application_email(
                    $pdo,
                    $recipients,
                    $subject,
                    $body,
                    $attachments,
                    (string) ($pending['candidate_email'] ?? ''),
                    (string) ($pending['candidate_name'] ?? ''),
                    $cc
                );

                if ($result['ok']) {
                    cpvia_mark_pending_sent($pdo, (int) $pending['id']);
                    // EMAIL_ONLY: remove temporary attachment files now that the
                    // email has been delivered. BACKEND_AND_EMAIL keeps them.
                    if ($mode === 'EMAIL_ONLY') {
                        cpvia_cleanup_pending_files(__DIR__, $pending);
                    }
                    $state = 'success';
                    $_SESSION['review_csrf'] = bin2hex(random_bytes(32));
                    $csrf = $_SESSION['review_csrf'];
                } else {
                    cpvia_mark_pending_failed($pdo, (int) $pending['id'], $result['error']);
                    $error = 'We could not send your application: ' . $result['error'] . ' You can edit and try again.';
                }
            }
        }
    }
}

$job_id = (int) ($pending['job_id'] ?? 0);
$back_url = $job_id > 0 ? 'apply?job_id=' . $job_id : 'careers';

include __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/CSS/apply_wizard.css">
<link rel="stylesheet" href="assets/CSS/review_email.css">

<div class="review-wrap">
<?php if ($state === 'invalid'): ?>
    <div class="review-state-card">
        <div class="review-state-icon review-icon-warn">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
        <h1>Review Link Not Found</h1>
        <p>This email review link is invalid or has already been completed. If you were submitting an application, please start again from the careers page.</p>
        <div class="review-state-actions"><a href="careers" class="apply-btn apply-btn-primary">View Open Positions</a></div>
    </div>

<?php elseif ($state === 'expired'): ?>
    <div class="review-state-card">
        <div class="review-state-icon review-icon-warn">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <h1>Review Session Expired</h1>
        <p>For security, this review link has expired. Please submit your application again to generate a fresh email.</p>
        <div class="review-state-actions"><a href="<?php echo htmlspecialchars($back_url); ?>" class="apply-btn apply-btn-primary">Start Again</a></div>
    </div>

<?php elseif ($state === 'already_sent'): ?>
    <div class="review-state-card">
        <div class="review-state-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h1>Application Already Sent</h1>
        <p>This application has already been emailed successfully. There is nothing more to do.</p>
        <div class="review-state-actions"><a href="careers" class="apply-btn apply-btn-primary">Back to Careers</a></div>
    </div>

<?php elseif ($state === 'success'): ?>
    <div class="review-state-card">
        <div class="review-state-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h1>Application Sent Successfully</h1>
        <p>Thank you<?php echo !empty($pending['candidate_name']) ? ', ' . htmlspecialchars($pending['candidate_name']) : ''; ?>. Your application for
            <strong><?php echo htmlspecialchars((string) $pending['job_title']); ?></strong> has been emailed to our team<?php echo !empty($_POST['send_copy']) ? ', and a copy was sent to you' : ''; ?>.</p>
        <div class="review-state-actions">
            <a href="careers" class="apply-btn apply-btn-primary">Back to Careers</a>
            <a href="/" class="apply-btn apply-btn-ghost">Back to Home</a>
        </div>
    </div>

<?php else: ?>
    <div class="review-head">
        <span class="review-eyebrow">Final step</span>
        <h1>Review your application email</h1>
        <p>We prepared this email from your application. You can edit the subject and message. The recipients and attachments are fixed and cannot be changed.</p>
    </div>

    <?php if ($error): ?>
        <div class="apply-alert review-alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="review_email" class="review-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="hidden" name="do" value="send">

        <div class="review-card-panel">
            <div class="review-row-field">
                <label>To <span class="review-lock" title="Fixed by the employer">&#128274; Read only</span></label>
                <div class="review-readonly-value">
                    <?php foreach ($recipients as $r): ?>
                        <span class="review-pill"><?php echo htmlspecialchars($r); ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($recipients)): ?><span class="review-muted">No recipients configured</span><?php endif; ?>
                </div>
            </div>

            <div class="review-row-two">
                <div class="review-row-field">
                    <label>From <span class="review-lock" title="Global sender configured in Settings">&#128274; Read only</span></label>
                    <div class="review-readonly-line"><?php echo htmlspecialchars($from_display); ?></div>
                </div>
                <div class="review-row-field">
                    <label>Reply-To <span class="review-lock" title="Your email — replies come back to you">&#128274; Read only</span></label>
                    <div class="review-readonly-line"><?php echo $reply_to_display !== '' ? htmlspecialchars($reply_to_display) : '<span class="review-muted">—</span>'; ?></div>
                </div>
            </div>

            <div class="review-row-field">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" maxlength="255" value="<?php echo htmlspecialchars($subject); ?>">
            </div>

            <div class="review-row-field">
                <label for="body">Message</label>
                <textarea id="body" name="body" rows="14"><?php echo htmlspecialchars($body); ?></textarea>
                <small class="review-hint">You may edit the greeting, body, closing, and add a personal note. Please keep it professional.</small>
            </div>

            <div class="review-row-field">
                <label>Attachments <span class="review-lock" title="Always attached">&#128274; Read only</span></label>
                <div class="review-attachments">
                    <?php foreach ($attachments_meta as $m): ?>
                        <div class="review-attachment<?php echo $m['exists'] ? '' : ' review-attachment-missing'; ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            <span class="review-attachment-label"><?php echo htmlspecialchars($m['label']); ?></span>
                            <span class="review-attachment-name"><?php echo htmlspecialchars($m['name']); ?></span>
                            <?php if (!$m['exists']): ?><span class="review-attachment-warn">file missing</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($attachments_meta)): ?><span class="review-muted">No attachments.</span><?php endif; ?>
                </div>
            </div>

            <div class="review-row-field">
                <label class="review-check">
                    <input type="checkbox" name="send_copy" value="1" <?php echo !empty($_POST['send_copy']) ? 'checked' : ''; ?>>
                    <span>Send me a copy of this application<?php echo !empty($pending['candidate_email']) ? ' (' . htmlspecialchars($pending['candidate_email']) . ')' : ''; ?></span>
                </label>
            </div>
        </div>

        <div class="review-nav">
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="apply-btn apply-btn-ghost">Back</a>
            <button type="submit" class="apply-btn apply-btn-primary" id="reviewSend">Send Application</button>
        </div>
    </form>

    <script>
    (function () {
        var form = document.querySelector('.review-form');
        var btn = document.getElementById('reviewSend');
        if (form && btn) {
            form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.textContent = 'Sending…';
            });
        }
    }());
    </script>
<?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
