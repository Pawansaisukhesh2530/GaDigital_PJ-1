<?php
/**
 * migrate_recruitment_db.php
 * -----------------------------------------------------------------------------
 * Safe, idempotent database migration for the CPVIA Recruitment Management
 * System.
 *
 * Purpose:
 *   Prepare the existing SQLite database for the upcoming 8-step Job Posting
 *   Wizard and 7-step Candidate Application Wizard WITHOUT breaking the
 *   currently working system.
 *
 * Guarantees:
 *   - Creates a timestamped backup before touching the database.
 *   - Never drops or recreates the existing `jobs` or `applications` tables.
 *   - Never deletes existing jobs, applications, admins, or resume files.
 *   - Only ADDS columns / tables / skills when they do not already exist.
 *   - Safe to run multiple times (idempotent).
 *   - Reuses the shared cpvia_db() PDO connection (WAL + busy_timeout).
 *
 * Run (Windows PowerShell):
 *   & C:\xampp\php\php.exe migrate_recruitment_db.php
 *
 * Or via browser:
 *   http://localhost/.../migrate_recruitment_db.php
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';

/* ----------------------------------------------------------------------------
 * Output helpers (work in both CLI and browser)
 * ------------------------------------------------------------------------- */
$IS_CLI = (php_sapi_name() === 'cli');

if (!$IS_CLI) {
    header('Content-Type: text/plain; charset=utf-8');
}

$REPORT = [
    'backup'                  => '',
    'tables_created'          => [],
    'tables_existing'         => [],
    'jobs_columns_added'      => [],
    'jobs_columns_existing'   => [],
    'apps_columns_added'      => [],
    'apps_columns_existing'   => [],
    'skills_inserted'         => [],
    'skills_existing'         => [],
    'errors'                  => [],
];

function out(string $line = ''): void
{
    echo $line . PHP_EOL;
}

function section(string $title): void
{
    out();
    out('==================================================================');
    out($title);
    out('==================================================================');
}

/* ----------------------------------------------------------------------------
 * Locate the database
 * ------------------------------------------------------------------------- */
$db_file = __DIR__ . '/admin/cpvia_database.sqlite';

section('CPVIA RECRUITMENT DB MIGRATION');
out('Started: ' . date('Y-m-d H:i:s'));
out('Database: ' . $db_file);

if (!file_exists($db_file)) {
    out();
    out('FATAL: Database file not found.');
    out('This migration only upgrades an EXISTING installation.');
    out('For a fresh install, run init_db.php first.');
    exit(1);
}

/* ----------------------------------------------------------------------------
 * STEP 1 - BACKUP SAFETY
 * ------------------------------------------------------------------------- */
section('STEP 1 - BACKUP');

$backup_dir = __DIR__ . '/admin/backups';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0777, true) && !is_dir($backup_dir)) {
        out('FATAL: Could not create backup directory: ' . $backup_dir);
        out('Migration aborted. No changes were made.');
        exit(1);
    }
    out('Created backup directory: ' . $backup_dir);
}

/*
 * Checkpoint the WAL first so the main .sqlite file contains all committed
 * data, then copy it. This produces a self-contained, restorable backup.
 */
try {
    $pdo = cpvia_db($db_file);
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
} catch (Throwable $e) {
    // Non-fatal: we can still copy the current file set.
    $REPORT['errors'][] = 'WAL checkpoint warning: ' . $e->getMessage();
    out('Warning: WAL checkpoint failed (' . $e->getMessage() . '). Continuing with file copy.');
}

$timestamp   = date('Ymd_His');
$backup_file = $backup_dir . '/cpvia_database_' . $timestamp . '.sqlite';

// Never overwrite an existing backup.
if (file_exists($backup_file)) {
    $backup_file = $backup_dir . '/cpvia_database_' . $timestamp . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.sqlite';
}

if (!copy($db_file, $backup_file)) {
    out('FATAL: Could not create backup at: ' . $backup_file);
    out('Migration aborted. No changes were made to the database.');
    exit(1);
}

// Also copy WAL/SHM sidecars if they still exist (defensive; usually cleared by checkpoint).
foreach (['-wal', '-shm'] as $suffix) {
    $sidecar = $db_file . $suffix;
    if (file_exists($sidecar)) {
        @copy($sidecar, $backup_file . $suffix);
    }
}

$REPORT['backup'] = $backup_file;
out('Backup created: ' . $backup_file);
out('Backup size: ' . number_format((int) filesize($backup_file)) . ' bytes');

/* ----------------------------------------------------------------------------
 * Introspection helpers
 * ------------------------------------------------------------------------- */
function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    // PRAGMA does not accept bound params; table name comes from a controlled list.
    $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (strcasecmp($row['name'], $column) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Add a column only if it does not already exist. Records the result.
 * NOTE: SQLite ALTER TABLE ADD COLUMN cannot use non-constant defaults
 * (e.g. CURRENT_TIMESTAMP), so timestamp columns are added as plain nullable.
 */
function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition, array &$added, array &$existing, array &$errors): void
{
    if (column_exists($pdo, $table, $column)) {
        $existing[] = $column;
        out("  [skip] $table.$column already exists");
        return;
    }
    try {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        $added[] = $column;
        out("  [add ] $table.$column ($definition)");
    } catch (Throwable $e) {
        $errors[] = "Failed adding $table.$column: " . $e->getMessage();
        out("  [ERR ] $table.$column -> " . $e->getMessage());
    }
}

function create_table_if_missing(PDO $pdo, string $table, string $sql, array &$created, array &$existing, array &$errors): void
{
    if (table_exists($pdo, $table)) {
        $existing[] = $table;
        out("  [skip] table '$table' already exists");
        return;
    }
    try {
        $pdo->exec($sql);
        $created[] = $table;
        out("  [new ] table '$table' created");
    } catch (Throwable $e) {
        $errors[] = "Failed creating table $table: " . $e->getMessage();
        out("  [ERR ] table '$table' -> " . $e->getMessage());
    }
}

/* ----------------------------------------------------------------------------
 * Enable FK enforcement for this migration connection
 * ------------------------------------------------------------------------- */
try {
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (Throwable $e) {
    $REPORT['errors'][] = 'Could not enable foreign_keys: ' . $e->getMessage();
}

/* ----------------------------------------------------------------------------
 * STEP 3 - EXPAND jobs TABLE (additive only)
 * ------------------------------------------------------------------------- */
section('STEP 3 - EXPAND jobs TABLE');

$job_columns = [
    'job_code'                => "TEXT",
    'work_mode'               => "TEXT",
    'number_of_openings'      => "INTEGER DEFAULT 1",
    'hiring_priority'         => "TEXT",
    'country'                 => "TEXT",
    'state'                   => "TEXT",
    'city'                    => "TEXT",
    'office_location'         => "TEXT",
    'remote_available'        => "INTEGER DEFAULT 0",
    'min_experience'          => "REAL",
    'max_experience'          => "REAL",
    'minimum_qualification'   => "TEXT",
    'degree'                  => "TEXT",
    'specialization'          => "TEXT",
    'salary_type'             => "TEXT",
    'min_salary'              => "REAL",
    'max_salary'              => "REAL",
    'currency'                => "TEXT",
    'responsibilities'        => "TEXT",
    'benefits'                => "TEXT",
    'preferred_notice_period' => "TEXT",
    'gender_preference'       => "TEXT DEFAULT 'Any'",
    'minimum_age'             => "INTEGER",
    'maximum_age'             => "INTEGER",
    'updated_at'              => "DATETIME", // nullable; app sets on update
];

foreach ($job_columns as $col => $def) {
    add_column_if_missing($pdo, 'jobs', $col, $def, $REPORT['jobs_columns_added'], $REPORT['jobs_columns_existing'], $REPORT['errors']);
}

/* ----------------------------------------------------------------------------
 * STEP 4 - EXPAND applications TABLE (additive only)
 * ------------------------------------------------------------------------- */
section('STEP 4 - EXPAND applications TABLE');

$app_columns = [
    'job_id'                => "INTEGER",           // soft link to jobs.id (no destructive FK rebuild)
    'current_location'      => "TEXT",
    'linkedin_profile'      => "TEXT",
    'portfolio_url'         => "TEXT",
    'why_interested'        => "TEXT",
    'why_cpvia'             => "TEXT",
    'willing_to_relocate'   => "INTEGER DEFAULT 0",
    'declaration_accurate'  => "INTEGER DEFAULT 0",
    'consent_data_storage'  => "INTEGER DEFAULT 0",
    'updated_at'            => "DATETIME",          // nullable; app sets on update
];

foreach ($app_columns as $col => $def) {
    add_column_if_missing($pdo, 'applications', $col, $def, $REPORT['apps_columns_added'], $REPORT['apps_columns_existing'], $REPORT['errors']);
}

/* ----------------------------------------------------------------------------
 * STEP 5 - skills master table
 * ------------------------------------------------------------------------- */
section('STEP 5 - skills TABLE');

create_table_if_missing($pdo, 'skills', "
    CREATE TABLE IF NOT EXISTS skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
", $REPORT['tables_created'], $REPORT['tables_existing'], $REPORT['errors']);

/* ----------------------------------------------------------------------------
 * STEP 6 - job_skills table
 * ------------------------------------------------------------------------- */
section('STEP 6 - job_skills TABLE');

create_table_if_missing($pdo, 'job_skills', "
    CREATE TABLE IF NOT EXISTS job_skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id INTEGER NOT NULL,
        skill_id INTEGER NOT NULL,
        skill_type TEXT NOT NULL DEFAULT 'required',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (job_id, skill_id, skill_type),
        FOREIGN KEY (job_id)  REFERENCES jobs(id)   ON DELETE CASCADE,
        FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    )
", $REPORT['tables_created'], $REPORT['tables_existing'], $REPORT['errors']);

/* ----------------------------------------------------------------------------
 * STEP 7 - application_professional_details table (one-to-one)
 * ------------------------------------------------------------------------- */
section('STEP 7 - application_professional_details TABLE');

create_table_if_missing($pdo, 'application_professional_details', "
    CREATE TABLE IF NOT EXISTS application_professional_details (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL UNIQUE,
        total_experience REAL,
        relevant_experience REAL,
        current_company TEXT,
        current_designation TEXT,
        current_ctc REAL,
        expected_ctc REAL,
        ctc_currency TEXT,
        notice_period TEXT,
        employment_status TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
    )
", $REPORT['tables_created'], $REPORT['tables_existing'], $REPORT['errors']);

/* ----------------------------------------------------------------------------
 * STEP 8 - application_education table (one-to-many)
 * ------------------------------------------------------------------------- */
section('STEP 8 - application_education TABLE');

create_table_if_missing($pdo, 'application_education', "
    CREATE TABLE IF NOT EXISTS application_education (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL,
        qualification TEXT,
        specialization TEXT,
        university_college TEXT,
        graduation_year INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
    )
", $REPORT['tables_created'], $REPORT['tables_existing'], $REPORT['errors']);

/* ----------------------------------------------------------------------------
 * STEP 9 - application_documents table (one-to-many)
 * ------------------------------------------------------------------------- */
section('STEP 9 - application_documents TABLE');

create_table_if_missing($pdo, 'application_documents', "
    CREATE TABLE IF NOT EXISTS application_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL,
        document_type TEXT NOT NULL DEFAULT 'resume',
        original_filename TEXT,
        stored_filename TEXT,
        file_path TEXT,
        mime_type TEXT,
        file_size INTEGER,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
    )
", $REPORT['tables_created'], $REPORT['tables_existing'], $REPORT['errors']);

/* ----------------------------------------------------------------------------
 * STEP 10 - application_skills table (many-to-many)
 * ------------------------------------------------------------------------- */
section('STEP 10 - application_skills TABLE');

create_table_if_missing($pdo, 'application_skills', "
    CREATE TABLE IF NOT EXISTS application_skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL,
        skill_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (application_id, skill_id),
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
        FOREIGN KEY (skill_id)       REFERENCES skills(id)       ON DELETE CASCADE
    )
", $REPORT['tables_created'], $REPORT['tables_existing'], $REPORT['errors']);

/* ----------------------------------------------------------------------------
 * STEP 5b - Seed master skills (INSERT OR IGNORE, no duplicates)
 * ------------------------------------------------------------------------- */
section('SEED - master skills');

$seed_skills = [
    'SAS',
    'R Programming',
    'Python',
    'CDISC',
    'SDTM',
    'ADaM',
    'Clinical Programming',
    'Biostatistics',
    'Power BI',
    'SQL',
    'AI',
    'Machine Learning',
];

if (table_exists($pdo, 'skills')) {
    try {
        // Short transaction to keep the DB unlocked as briefly as possible.
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT OR IGNORE INTO skills (name, is_active) VALUES (?, 1)');
        $check  = $pdo->prepare('SELECT COUNT(*) FROM skills WHERE name = ?');

        foreach ($seed_skills as $skill) {
            $check->execute([$skill]);
            $already = (int) $check->fetchColumn() > 0;

            if ($already) {
                $REPORT['skills_existing'][] = $skill;
                out("  [skip] skill '$skill' already exists");
            } else {
                $insert->execute([$skill]);
                $REPORT['skills_inserted'][] = $skill;
                out("  [add ] skill '$skill' inserted");
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $REPORT['errors'][] = 'Skill seeding failed: ' . $e->getMessage();
        out('  [ERR ] skill seeding -> ' . $e->getMessage());
    }
} else {
    $REPORT['errors'][] = 'skills table missing; cannot seed skills.';
    out('  [ERR ] skills table missing; cannot seed skills.');
}

/* ----------------------------------------------------------------------------
 * STEP 15 - VERIFY
 * ------------------------------------------------------------------------- */
section('STEP 15 - VERIFY REQUIRED TABLES');

$required_tables = [
    'jobs',
    'applications',
    'skills',
    'job_skills',
    'application_professional_details',
    'application_education',
    'application_documents',
    'application_skills',
];

$all_present = true;
foreach ($required_tables as $t) {
    $present = table_exists($pdo, $t);
    $all_present = $all_present && $present;
    out(sprintf('  %-34s %s', $t, $present ? 'OK' : 'MISSING'));
}

// Preserve admins table (informational).
out();
out('  admins table preserved: ' . (table_exists($pdo, 'admins') ? 'YES' : 'NO'));

/* ----------------------------------------------------------------------------
 * FINAL REPORT
 * ------------------------------------------------------------------------- */
section('FINAL REPORT');

out('Backup file        : ' . $REPORT['backup']);
out();
out('Tables created     : ' . ($REPORT['tables_created'] ? implode(', ', $REPORT['tables_created']) : '(none - all already existed)'));
out('Tables existing    : ' . ($REPORT['tables_existing'] ? implode(', ', $REPORT['tables_existing']) : '(none)'));
out();
out('jobs cols added    : ' . ($REPORT['jobs_columns_added'] ? implode(', ', $REPORT['jobs_columns_added']) : '(none)'));
out('jobs cols existing : ' . ($REPORT['jobs_columns_existing'] ? implode(', ', $REPORT['jobs_columns_existing']) : '(none)'));
out();
out('apps cols added    : ' . ($REPORT['apps_columns_added'] ? implode(', ', $REPORT['apps_columns_added']) : '(none)'));
out('apps cols existing : ' . ($REPORT['apps_columns_existing'] ? implode(', ', $REPORT['apps_columns_existing']) : '(none)'));
out();
out('Skills inserted    : ' . ($REPORT['skills_inserted'] ? implode(', ', $REPORT['skills_inserted']) : '(none)'));
out('Skills existing    : ' . ($REPORT['skills_existing'] ? implode(', ', $REPORT['skills_existing']) : '(none)'));
out();

if ($REPORT['errors']) {
    out('ERRORS:');
    foreach ($REPORT['errors'] as $err) {
        out('  - ' . $err);
    }
} else {
    out('Errors             : (none)');
}

out();
out('Required tables all present: ' . ($all_present ? 'YES' : 'NO'));
out('Migration finished : ' . date('Y-m-d H:i:s'));
out();
out($all_present && !$REPORT['errors']
    ? 'RESULT: MIGRATION COMPLETED SUCCESSFULLY.'
    : 'RESULT: MIGRATION COMPLETED WITH WARNINGS - review the report above.');

exit($all_present ? 0 : 1);
