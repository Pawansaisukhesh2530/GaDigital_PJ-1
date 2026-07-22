<?php
/**
 * migrate_application_delivery.php
 * -----------------------------------------------------------------------------
 * Safe, idempotent migration for the Application Delivery feature.
 *
 * Adds (additive only — never drops or rewrites existing tables):
 *   jobs.submission_mode   TEXT DEFAULT 'BACKEND_ONLY'   (BACKEND_ONLY|EMAIL_ONLY|BACKEND_AND_EMAIL)
 *   jobs.recipient_emails  TEXT
 *   app_settings table                 (global SMTP config + email template)
 *   pending_email_applications table   (durable email-review workflow)
 *
 * Existing jobs default to BACKEND_ONLY, so current behaviour is unchanged.
 *
 * Run (Windows):  & C:\xampp\php\php.exe migrate_application_delivery.php
 * Or via browser: http://localhost/.../migrate_application_delivery.php
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings_helpers.php';

$IS_CLI = (php_sapi_name() === 'cli');
if (!$IS_CLI) {
    header('Content-Type: text/plain; charset=utf-8');
}
function out(string $l = ''): void { echo $l . PHP_EOL; }
function sect(string $t): void { out(); out('=================================================================='); out($t); out('=================================================================='); }

$db_file = __DIR__ . '/admin/cpvia_database.sqlite';

sect('CPVIA APPLICATION DELIVERY MIGRATION');
out('Started : ' . date('Y-m-d H:i:s'));
out('Database: ' . $db_file);

if (!file_exists($db_file)) {
    out();
    out('FATAL: Database not found. Run init_db.php first for a fresh install.');
    exit(1);
}

/* ---- Backup ---- */
sect('STEP 1 - BACKUP');
$backup_dir = __DIR__ . '/admin/backups';
if (!is_dir($backup_dir) && !mkdir($backup_dir, 0777, true) && !is_dir($backup_dir)) {
    out('FATAL: cannot create backup dir: ' . $backup_dir);
    exit(1);
}
try {
    $pdo = cpvia_db($db_file);
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
} catch (Throwable $e) {
    out('Warning: WAL checkpoint failed (' . $e->getMessage() . '). Continuing.');
    $pdo = cpvia_db($db_file);
}
$backup_file = $backup_dir . '/cpvia_database_' . date('Ymd_His') . '_predelivery.sqlite';
if (!copy($db_file, $backup_file)) {
    out('FATAL: backup failed at ' . $backup_file);
    exit(1);
}
out('Backup created: ' . $backup_file);

/* ---- Introspection helpers ---- */
function col_exists(PDO $pdo, string $table, string $column): bool
{
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (strcasecmp($r['name'], $column) === 0) { return true; }
    }
    return false;
}
function add_col(PDO $pdo, string $table, string $col, string $def): void
{
    if (col_exists($pdo, $table, $col)) {
        out("  [skip] $table.$col already exists");
        return;
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
    out("  [add ] $table.$col ($def)");
}

/* ---- STEP 2: jobs columns ---- */
sect('STEP 2 - EXPAND jobs TABLE');
add_col($pdo, 'jobs', 'submission_mode', "TEXT DEFAULT 'BACKEND_ONLY'");
add_col($pdo, 'jobs', 'recipient_emails', "TEXT");

// Backfill NULL submission_mode on any legacy rows.
$pdo->exec("UPDATE jobs SET submission_mode = 'BACKEND_ONLY' WHERE submission_mode IS NULL OR submission_mode = ''");
out('  [ok  ] existing jobs normalised to BACKEND_ONLY where empty');

/* ---- STEP 3: app_settings ---- */
sect('STEP 3 - app_settings TABLE');
cpvia_ensure_settings_table($pdo);
out('  [ok  ] app_settings ready');

// Seed defaults only for keys that are not present yet (idempotent).
$existing = $pdo->query("SELECT key FROM app_settings")->fetchAll(PDO::FETCH_COLUMN);
$existing = array_map('strval', $existing);
$toSeed = [];
foreach (cpvia_settings_defaults() as $k => $v) {
    if (!in_array($k, $existing, true)) {
        $toSeed[$k] = $v;
    }
}
if ($toSeed) {
    cpvia_save_settings($pdo, $toSeed);
    out('  [seed] default settings added: ' . implode(', ', array_keys($toSeed)));
} else {
    out('  [skip] all settings already present');
}

/* ---- STEP 4: pending_email_applications ---- */
sect('STEP 4 - pending_email_applications TABLE');
$pdo->exec("CREATE TABLE IF NOT EXISTS pending_email_applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL UNIQUE,
    job_id INTEGER,
    application_id INTEGER,
    mode TEXT NOT NULL,
    recipient_emails TEXT NOT NULL,
    candidate_name TEXT,
    candidate_email TEXT,
    candidate_phone TEXT,
    job_title TEXT,
    subject TEXT,
    body TEXT,
    resume_path TEXT,
    resume_original TEXT,
    cover_path TEXT,
    cover_original TEXT,
    payload TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    expires_at DATETIME
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pending_token ON pending_email_applications(token)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pending_status ON pending_email_applications(status)");
out('  [ok  ] pending_email_applications ready');

/* ---- Verify ---- */
sect('VERIFY');
$ok = col_exists($pdo, 'jobs', 'submission_mode')
    && col_exists($pdo, 'jobs', 'recipient_emails');
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
$ok = $ok && in_array('app_settings', $tables, true) && in_array('pending_email_applications', $tables, true);

out('  jobs.submission_mode        : ' . (col_exists($pdo, 'jobs', 'submission_mode') ? 'OK' : 'MISSING'));
out('  jobs.recipient_emails       : ' . (col_exists($pdo, 'jobs', 'recipient_emails') ? 'OK' : 'MISSING'));
out('  app_settings                : ' . (in_array('app_settings', $tables, true) ? 'OK' : 'MISSING'));
out('  pending_email_applications  : ' . (in_array('pending_email_applications', $tables, true) ? 'OK' : 'MISSING'));

out();
out($ok ? 'RESULT: MIGRATION COMPLETED SUCCESSFULLY.' : 'RESULT: MIGRATION COMPLETED WITH WARNINGS.');
out('Finished: ' . date('Y-m-d H:i:s'));
exit($ok ? 0 : 1);
