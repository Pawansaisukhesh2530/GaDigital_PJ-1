<?php
/**
 * settings.php — Global Application Settings (Admin)
 * -----------------------------------------------------------------------------
 * Configure GLOBAL SMTP delivery and the configurable email template used when
 * a job's Application Delivery mode sends applications by email. Also provides a
 * "Send test email" action to verify the SMTP configuration end-to-end.
 *
 * SMTP settings are global (never per-job). Per-job delivery behaviour lives on
 * the jobs table (submission_mode + recipient_emails).
 * -----------------------------------------------------------------------------
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../settings_helpers.php';
require_once __DIR__ . '/../email_helper.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$pdo = cpvia_db($db_file);

$flash = '';
$error = '';
$warning = '';

$encryptions = ['none', 'ssl', 'tls'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? 'save';

    if (!cpvia_csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Your session has expired. Please refresh and try again.';
    } else {
        // Collect + normalise input.
        $enc = in_array($_POST['smtp_encryption'] ?? '', $encryptions, true) ? $_POST['smtp_encryption'] : 'tls';
        $port = (string) (int) ($_POST['smtp_port'] ?? 587);
        if ((int) $port <= 0) { $port = '587'; }

        $from_email = trim((string) ($_POST['smtp_from_email'] ?? ''));
        $test_to = trim((string) ($_POST['test_email'] ?? ''));

        // Preserve the existing password when the field is left blank.
        $current = cpvia_get_settings($pdo);
        $password_in = (string) ($_POST['smtp_password'] ?? '');
        $password = $password_in === '' ? $current['smtp_password'] : $password_in;

        $incoming = [
            'smtp_host'        => trim((string) ($_POST['smtp_host'] ?? '')),
            'smtp_port'        => $port,
            'smtp_username'    => trim((string) ($_POST['smtp_username'] ?? '')),
            'smtp_password'    => $password,
            'smtp_encryption'  => $enc,
            'smtp_from_name'   => cpvia_sanitize_header_line((string) ($_POST['smtp_from_name'] ?? '')),
            'smtp_from_email'  => $from_email,
            'company_name'     => trim((string) ($_POST['company_name'] ?? 'CPVIA')),
            'email_subject_template' => (string) ($_POST['email_subject_template'] ?? ''),
            'email_body_template'    => (string) ($_POST['email_body_template'] ?? ''),
        ];

        // Validation
        if ($from_email !== '' && !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'From Email must be a valid email address.';
        } elseif ($incoming['email_subject_template'] === '') {
            $error = 'The email subject template cannot be empty.';
        } elseif ($incoming['email_body_template'] === '') {
            $error = 'The email body template cannot be empty.';
        }

        // Persist first (item 9: the test always uses freshly-saved values).
        if ($error === '') {
            try {
                cpvia_save_settings($pdo, $incoming);
            } catch (Throwable $e) {
                $error = 'Could not save settings: ' . $e->getMessage();
            }
        }

        // Which required SMTP fields are still missing after this save?
        $missing = $error === '' ? cpvia_smtp_missing_fields($incoming) : [];

        if ($error === '' && $do === 'test') {
            if ($test_to === '' || !filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid "Send test email to" address to run the test.';
            } elseif (!empty($missing)) {
                // Block the test with a precise, per-field explanation.
                $error = 'Settings saved, but the test cannot run yet — please complete: '
                    . implode('; ', $missing) . '.';
            } else {
                $result = cpvia_send_application_email(
                    $pdo,
                    [$test_to],
                    'CPVIA SMTP Test — Configuration OK',
                    "This is a test email from your CPVIA recruitment system.\n\n"
                        . "If you received this, your SMTP settings are working correctly.\n\n"
                        . 'Sent at ' . date('Y-m-d H:i:s'),
                    []
                );
                if ($result['ok']) {
                    $flash = 'Settings saved. Test email sent to ' . htmlspecialchars($test_to) . '.';
                } else {
                    $error = 'Settings saved, but the test email failed: ' . $result['error'];
                }
            }
        } elseif ($error === '') {
            if (!empty($missing)) {
                // Saved, but incomplete — tell the admin exactly what to add.
                $warning = 'Settings saved, but email delivery is not ready yet. Please complete: '
                    . implode('; ', $missing) . '.';
            } else {
                $flash = 'Settings saved successfully. SMTP is configured.';
            }
        }
    }
}

$s = cpvia_get_settings($pdo);
$placeholders = cpvia_email_placeholders();

$page_title = 'Settings';
$active_nav = 'settings';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Settings', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?php echo $flash; ?></div>
<?php endif; ?>
<?php if ($warning): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php
// Persistent status banner: always tells the admin whether SMTP is ready, and
// exactly which required fields are still missing.
$smtp_missing = cpvia_smtp_missing_fields($s);
?>
<div class="smtp-status <?php echo empty($smtp_missing) ? 'smtp-status-ok' : 'smtp-status-bad'; ?>">
    <?php if (empty($smtp_missing)): ?>
        <strong>SMTP is configured.</strong> Email delivery is ready. Use "Save &amp; Send Test" to confirm end-to-end.
    <?php else: ?>
        <strong>SMTP is not ready.</strong> Complete the following required field<?php echo count($smtp_missing) > 1 ? 's' : ''; ?>:
        <ul class="smtp-status-list">
            <?php foreach ($smtp_missing as $m): ?>
                <li><?php echo htmlspecialchars($m); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<form method="POST" action="settings.php" class="settings-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">
    <input type="hidden" name="do" id="settingsDo" value="save">

    <div class="form-section-card">
        <h3>SMTP Configuration <span class="settings-scope-pill">Global</span></h3>
        <p class="form-section-sub">Used for all jobs that deliver applications by email. Configured once, applies everywhere.</p>

        <div class="wiz-grid-2">
            <div class="form-group">
                <label for="smtp_host">SMTP Host</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($s['smtp_host']); ?>" placeholder="e.g. smtp.gmail.com">
            </div>
            <div class="form-group">
                <label for="smtp_port">SMTP Port</label>
                <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($s['smtp_port']); ?>" placeholder="587" min="1" max="65535">
                <small class="field-hint">Common: 587 (TLS/STARTTLS), 465 (SSL), 25 (none).</small>
            </div>
        </div>

        <div class="wiz-grid-2">
            <div class="form-group">
                <label for="smtp_username">SMTP Username</label>
                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($s['smtp_username']); ?>" autocomplete="off" placeholder="Usually your email address">
            </div>
            <div class="form-group">
                <label for="smtp_password">SMTP Password</label>
                <input type="password" id="smtp_password" name="smtp_password" value="" autocomplete="new-password" placeholder="<?php echo $s['smtp_password'] !== '' ? '•••••••• (leave blank to keep)' : 'Enter password'; ?>">
                <small class="field-hint">Leave blank to keep the current password unchanged.</small>
            </div>
        </div>

        <div class="wiz-grid-3">
            <div class="form-group">
                <label for="smtp_encryption">Encryption</label>
                <select id="smtp_encryption" name="smtp_encryption">
                    <?php foreach ($encryptions as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo $s['smtp_encryption'] === $e ? 'selected' : ''; ?>><?php echo strtoupper($e); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="smtp_from_name">From Name</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($s['smtp_from_name']); ?>" placeholder="CPVIA Careers">
            </div>
            <div class="form-group">
                <label for="smtp_from_email">From Email</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($s['smtp_from_email']); ?>" placeholder="careers@cpvia.com">
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <h3>Email Template</h3>
        <p class="form-section-sub">The default subject and body generated for application emails. Candidates can edit these before sending. Use the placeholders below.</p>

        <div class="form-group">
            <label for="company_name">Company Name</label>
            <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($s['company_name']); ?>" placeholder="CPVIA">
        </div>

        <div class="form-group">
            <label for="email_subject_template">Subject Template</label>
            <input type="text" id="email_subject_template" name="email_subject_template" value="<?php echo htmlspecialchars($s['email_subject_template']); ?>">
        </div>

        <div class="form-group">
            <label for="email_body_template">Body Template</label>
            <textarea id="email_body_template" name="email_body_template" rows="12"><?php echo htmlspecialchars($s['email_body_template']); ?></textarea>
        </div>

        <div class="settings-placeholders">
            <span class="settings-placeholders-title">Available placeholders</span>
            <div class="settings-placeholder-chips">
                <?php foreach ($placeholders as $ph => $desc): ?>
                    <span class="settings-placeholder-chip" title="<?php echo htmlspecialchars($desc); ?>"><?php echo htmlspecialchars($ph); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <h3>Test Email</h3>
        <p class="form-section-sub">Send a test email to confirm your SMTP settings work. Saves your settings first.</p>
        <div class="wiz-grid-2">
            <div class="form-group">
                <label for="test_email">Send test email to</label>
                <input type="email" id="test_email" name="test_email" value="" placeholder="you@example.com">
            </div>
            <div class="form-group settings-test-btn-wrap">
                <button type="submit" class="btn-outline-pill" id="btnSendTest">Save &amp; Send Test</button>
            </div>
        </div>
    </div>

    <div class="wizard-nav">
        <a href="index.php" class="wizard-link-cancel">Cancel</a>
        <button type="submit" class="btn-primary-pill" id="btnSaveSettings">Save Settings</button>
    </div>
</form>

<script>
(function () {
    var doField = document.getElementById('settingsDo');
    var btnSave = document.getElementById('btnSaveSettings');
    var btnTest = document.getElementById('btnSendTest');
    if (btnSave) { btnSave.addEventListener('click', function () { doField.value = 'save'; }); }
    if (btnTest) { btnTest.addEventListener('click', function () { doField.value = 'test'; }); }
}());
</script>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
