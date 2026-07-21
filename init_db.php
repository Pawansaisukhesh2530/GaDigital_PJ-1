<?php
require_once __DIR__ . '/db.php';

$admin_dir   = __DIR__ . '/admin';
$uploads_dir = __DIR__ . '/uploads/resumes';

if (!file_exists($admin_dir)) {
    mkdir($admin_dir, 0777, true);
}
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

$db_file = defined('CPVIA_INIT_DB_PATH') ? CPVIA_INIT_DB_PATH : $admin_dir . '/cpvia_database.sqlite';

try {
    $pdo = cpvia_db($db_file);
    $pdo->exec('PRAGMA foreign_keys = ON');

    /* ---------------- Core: jobs (full schema) ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        department TEXT NOT NULL,
        location TEXT NOT NULL,
        employment_type TEXT NOT NULL,
        description TEXT NOT NULL,
        requirements TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        -- Expanded fields for the 8-step Job Posting Wizard
        job_code TEXT,
        work_mode TEXT,
        number_of_openings INTEGER DEFAULT 1,
        hiring_priority TEXT,
        country TEXT,
        state TEXT,
        city TEXT,
        office_location TEXT,
        remote_available INTEGER DEFAULT 0,
        min_experience REAL,
        max_experience REAL,
        minimum_qualification TEXT,
        degree TEXT,
        specialization TEXT,
        salary_type TEXT,
        min_salary REAL,
        max_salary REAL,
        currency TEXT,
        responsibilities TEXT,
        benefits TEXT,
        preferred_notice_period TEXT,
        gender_preference TEXT DEFAULT 'Any',
        minimum_age INTEGER,
        maximum_age INTEGER,
        updated_at DATETIME
    )");

    /* ---------------- Core: applications (full schema) ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_title TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT,
        cover_letter TEXT,
        resume_path TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'New',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        -- Expanded fields for the 7-step Candidate Application Wizard
        job_id INTEGER,
        current_location TEXT,
        linkedin_profile TEXT,
        portfolio_url TEXT,
        why_interested TEXT,
        why_cpvia TEXT,
        willing_to_relocate INTEGER DEFAULT 0,
        declaration_accurate INTEGER DEFAULT 0,
        consent_data_storage INTEGER DEFAULT 0,
        updated_at DATETIME
    )");

    /* ---------------- Admins ---------------- */
    cpvia_ensure_admins_table($pdo);

    /* ---------------- Skills master ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    /* ---------------- job_skills ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id INTEGER NOT NULL,
        skill_id INTEGER NOT NULL,
        skill_type TEXT NOT NULL DEFAULT 'required',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (job_id, skill_id, skill_type),
        FOREIGN KEY (job_id)  REFERENCES jobs(id)   ON DELETE CASCADE,
        FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    )");

    /* ---------------- application_professional_details ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS application_professional_details (
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
    )");

    /* ---------------- application_education ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS application_education (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL,
        qualification TEXT,
        specialization TEXT,
        university_college TEXT,
        graduation_year INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
    )");

    /* ---------------- application_documents ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS application_documents (
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
    )");

    /* ---------------- application_skills ---------------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS application_skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL,
        skill_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (application_id, skill_id),
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
        FOREIGN KEY (skill_id)       REFERENCES skills(id)       ON DELETE CASCADE
    )");

    /* ---------------- Performance indexes (non-destructive) ---------------- */
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_applications_status ON applications(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_applications_job_id ON applications(job_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_applications_created ON applications(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_app_education_app ON application_education(application_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_app_documents_app ON application_documents(application_id)");

    /* ---------------- Seed master skills (idempotent) ---------------- */
    $seed_skills = [
        'SAS', 'R Programming', 'Python', 'CDISC', 'SDTM', 'ADaM',
        'Clinical Programming', 'Biostatistics', 'Power BI', 'SQL',
        'AI', 'Machine Learning',
    ];
    $insert = $pdo->prepare('INSERT OR IGNORE INTO skills (name, is_active) VALUES (?, 1)');
    foreach ($seed_skills as $skill) {
        $insert->execute([$skill]);
    }

    echo "Database initialized successfully (full schema).\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
