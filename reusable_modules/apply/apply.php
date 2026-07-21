<?php
/**
 * apply.php — Public 7-Step Candidate Application Wizard
 * -----------------------------------------------------------------------------
 * Flow: 1) Resume Upload & AI Preparation  2) Personal  3) Professional
 *       4) Education  5) Skills  6) Additional Questions  7) Review & Submit
 *
 * Stores a complete application across the migrated recruitment schema
 * (applications, application_professional_details, application_education,
 * application_documents, application_skills) atomically, while preserving the
 * legacy fields the Admin still depends on (job_title, name, email, phone,
 * resume_path). Reuses the public CPVIA design system. No candidate login.
 *
 * FUTURE AI AUTO-FILL: Step 1 collects the resume first. A future parser can
 * populate later steps by writing values into the matching field ids / the
 * localStorage draft and calling the JS hook window.cpviaApplyAutofill(data).
 * No parsing is performed today.
 * -----------------------------------------------------------------------------
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/apply_helpers.php';

$db_file = __DIR__ . '/admin/cpvia_database.sqlite';
$uploads_dir = __DIR__ . '/uploads/resumes';

// Lightweight session for CSRF only (no candidate accounts).
// Use a local, writable session directory so we don't depend on the server's
// default save path (e.g. C:\xampp\tmp) being writable.
if (session_status() === PHP_SESSION_NONE) {
    $apply_sess_dir = __DIR__ . '/sessions';
    if (!is_dir($apply_sess_dir)) {
        @mkdir($apply_sess_dir, 0700, true);
    }
    if (is_dir($apply_sess_dir) && is_writable($apply_sess_dir)) {
        session_save_path($apply_sess_dir);
    }
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    @session_start();
}
if (empty($_SESSION['apply_csrf'])) {
    $_SESSION['apply_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['apply_csrf'];

$pdo = cpvia_db($db_file);

$job_id = 0;
if (isset($_POST['job_id'])) {
    $job_id = (int) $_POST['job_id'];
} elseif (isset($_GET['job_id'])) {
    $job_id = (int) $_GET['job_id'];
}

$job = cpvia_apply_fetch_job($pdo, $job_id);
$all_skills = cpvia_apply_fetch_skills($pdo);
$skill_ids_valid = array_map(static fn($s) => $s['id'], $all_skills);

$errors = [];
$old = [];
$success = false;
$success_data = [];
$error_step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $csrf_ok = isset($_POST['csrf_token']) && hash_equals($csrf, (string) $_POST['csrf_token']);

    if (!$csrf_ok) {
        $errors['_global'] = 'Your session expired. Please review your details and submit again.';
    } elseif (!$job) {
        $errors['_global'] = 'This position is no longer accepting applications.';
    } else {
        [$errors, $clean] = cpvia_apply_validate($_POST);

        // Keep only skills that really exist in the master table.
        $clean['skills'] = array_values(array_intersect($clean['skills'], $skill_ids_valid));

        // Accidental rapid double-submit guard (same email + job within 60s).
        $duplicate = false;
        if (!$errors) {
            try {
                $dupStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM applications
                     WHERE email = ? AND job_id = ? AND created_at > datetime('now', '-60 seconds')"
                );
                $dupStmt->execute([$clean['email'], $job_id]);
                $duplicate = ((int) $dupStmt->fetchColumn()) > 0;
            } catch (Throwable $e) {
                $duplicate = false;
            }
        }

        if (!$errors && $duplicate) {
            // Treat as already submitted; do not create another record.
            $success = true;
            $success_data = [
                'job_title' => $job['title'],
                'name' => $clean['full_name'],
                'ref' => null,
                'duplicate' => true,
            ];
            $_SESSION['apply_csrf'] = bin2hex(random_bytes(32));
        } elseif (!$errors) {
            // Validation passed — now handle uploads (so we never orphan files
            // for a form that had field errors).
            $uploaded_paths = [];
            $resume = cpvia_apply_store_upload($_FILES['resume'] ?? [], $uploads_dir, 'resume', true);
            if (!$resume['ok']) {
                $errors['resume'] = $resume['error'];
            } elseif (!empty($resume['stored'])) {
                $uploaded_paths[] = $resume['path'];
            }

            $cover = ['ok' => true, 'stored' => null];
            if (!$errors) {
                $cover = cpvia_apply_store_upload($_FILES['cover_letter_file'] ?? [], $uploads_dir, 'cover', false);
                if (!$cover['ok']) {
                    $errors['cover_letter_file'] = $cover['error'];
                } elseif (!empty($cover['stored'])) {
                    $uploaded_paths[] = $cover['path'];
                }
            }

            if ($errors) {
                // Clean up anything already written to disk.
                foreach ($uploaded_paths as $p) {
                    if (is_file($p)) { @unlink($p); }
                }
            } else {
                // ---- Atomic multi-table write ----
                try {
                    $pdo->beginTransaction();
                    $now = date('Y-m-d H:i:s');

                    $insApp = $pdo->prepare(
                        "INSERT INTO applications
                            (job_title, name, email, phone, cover_letter, resume_path, status,
                             job_id, current_location, linkedin_profile, portfolio_url,
                             why_interested, why_cpvia, willing_to_relocate,
                             declaration_accurate, consent_data_storage, updated_at)
                         VALUES
                            (:job_title, :name, :email, :phone, :cover_letter, :resume_path, 'New',
                             :job_id, :current_location, :linkedin_profile, :portfolio_url,
                             :why_interested, :why_cpvia, :willing_to_relocate,
                             :declaration_accurate, :consent_data_storage, :updated_at)"
                    );
                    $insApp->execute([
                        ':job_title' => $job['title'],
                        ':name' => $clean['full_name'],
                        ':email' => $clean['email'],
                        ':phone' => $clean['mobile'],
                        ':cover_letter' => null,
                        ':resume_path' => $resume['stored'],
                        ':job_id' => $job_id,
                        ':current_location' => $clean['current_location'],
                        ':linkedin_profile' => $clean['linkedin_profile'] !== '' ? $clean['linkedin_profile'] : null,
                        ':portfolio_url' => $clean['portfolio_url'] !== '' ? $clean['portfolio_url'] : null,
                        ':why_interested' => $clean['why_interested'] !== '' ? $clean['why_interested'] : null,
                        ':why_cpvia' => $clean['why_cpvia'] !== '' ? $clean['why_cpvia'] : null,
                        ':willing_to_relocate' => $clean['willing_to_relocate'],
                        ':declaration_accurate' => $clean['declaration_accurate'],
                        ':consent_data_storage' => $clean['consent_data_storage'],
                        ':updated_at' => $now,
                    ]);
                    $app_id = (int) $pdo->lastInsertId();

                    $pdo->prepare(
                        "INSERT INTO application_professional_details
                            (application_id, total_experience, relevant_experience, current_company,
                             current_designation, current_ctc, expected_ctc, ctc_currency,
                             notice_period, employment_status)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    )->execute([
                        $app_id, $clean['total_experience'], $clean['relevant_experience'],
                        $clean['current_company'] !== '' ? $clean['current_company'] : null,
                        $clean['current_designation'] !== '' ? $clean['current_designation'] : null,
                        $clean['current_ctc'], $clean['expected_ctc'], $clean['ctc_currency'],
                        $clean['notice_period'], $clean['employment_status'],
                    ]);

                    $pdo->prepare(
                        "INSERT INTO application_education
                            (application_id, qualification, specialization, university_college, graduation_year)
                         VALUES (?,?,?,?,?)"
                    )->execute([
                        $app_id, $clean['qualification'],
                        $clean['specialization'] !== '' ? $clean['specialization'] : null,
                        $clean['university_college'] !== '' ? $clean['university_college'] : null,
                        $clean['graduation_year'],
                    ]);

                    $insDoc = $pdo->prepare(
                        "INSERT INTO application_documents
                            (application_id, document_type, original_filename, stored_filename, file_path, mime_type, file_size)
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    $insDoc->execute([
                        $app_id, 'resume', $resume['original'], $resume['stored'],
                        'uploads/resumes/' . $resume['stored'], $resume['mime'], $resume['size'],
                    ]);
                    if (!empty($cover['stored'])) {
                        $insDoc->execute([
                            $app_id, 'cover_letter', $cover['original'], $cover['stored'],
                            'uploads/resumes/' . $cover['stored'], $cover['mime'], $cover['size'],
                        ]);
                    }

                    if (!empty($clean['skills'])) {
                        $insSkill = $pdo->prepare(
                            "INSERT OR IGNORE INTO application_skills (application_id, skill_id) VALUES (?,?)"
                        );
                        foreach ($clean['skills'] as $sid) {
                            $insSkill->execute([$app_id, $sid]);
                        }
                    }

                    $pdo->commit();

                    $success = true;
                    $success_data = [
                        'job_title' => $job['title'],
                        'name' => $clean['full_name'],
                        'ref' => 'CPVIA-APP-' . str_pad((string) $app_id, 5, '0', STR_PAD_LEFT),
                        'duplicate' => false,
                    ];
                    $_SESSION['apply_csrf'] = bin2hex(random_bytes(32));
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    foreach ($uploaded_paths as $p) {
                        if (is_file($p)) { @unlink($p); }
                    }
                    $errors['_global'] = 'We could not submit your application due to a server error. Please try again.';
                }
            }
        }
    }

    if ($errors) {
        // Jump to the earliest step that has an error.
        $steps = [8];
        foreach (array_keys($errors) as $field) {
            if ($field === '_global') { continue; }
            $steps[] = cpvia_apply_field_step($field);
        }
        $error_step = min($steps);
        if ($error_step === 8) { $error_step = 1; }
    }
}

// Options + data for the view / JS.
$emp_statuses = cpvia_apply_employment_statuses();
$qualifications = cpvia_apply_qualifications();
$currencies = cpvia_apply_currencies();
$max_grad_year = (int) date('Y') + 6;

$skills_json = json_encode($all_skills, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$error_fields_json = json_encode(array_keys($errors));

/** Escape helper for repopulating inputs. */
function av(array $old, string $key, string $default = ''): string
{
    return htmlspecialchars((string) ($old[$key] ?? $default), ENT_QUOTES);
}

include __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="assets/CSS/apply_wizard.css">

<div class="apply-wrap">
<?php if ($success): ?>
    <div class="apply-success">
        <div class="apply-success-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h1>Application Submitted Successfully</h1>
        <p>Thank you<?php echo $success_data['name'] ? ', ' . htmlspecialchars($success_data['name']) : ''; ?>. Your application for
            <strong><?php echo htmlspecialchars($success_data['job_title']); ?></strong> has been received.
            <?php if (!empty($success_data['duplicate'])): ?><br><span class="apply-note-inline">It looks like you already submitted this application a moment ago — we kept your original submission.</span><?php endif; ?>
        </p>
        <?php if (!empty($success_data['ref'])): ?>
            <div class="apply-ref">Reference ID: <strong><?php echo htmlspecialchars($success_data['ref']); ?></strong></div>
        <?php endif; ?>
        <div class="apply-success-actions">
            <a href="careers" class="apply-btn apply-btn-primary">Back to Careers</a>
            <a href="/" class="apply-btn apply-btn-ghost">Back to Home</a>
        </div>
    </div>
    <script>try { Object.keys(localStorage).filter(function(k){return k.indexOf('cpvia_application_draft_')===0;}).forEach(function(k){localStorage.removeItem(k);}); } catch(e){}</script>

<?php elseif (!$job): ?>
    <div class="apply-success">
        <div class="apply-success-icon apply-icon-warn">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
        <h1>Position Not Available</h1>
        <p>This position is no longer accepting applications, or the link is invalid. Please browse our current openings.</p>
        <div class="apply-success-actions">
            <a href="careers" class="apply-btn apply-btn-primary">View Open Positions</a>
        </div>
    </div>

<?php else: ?>
    <!-- Compact job summary bar -->
    <div class="apply-summary">
        <a class="apply-summary-back" href="careers" aria-label="Back to Careers">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span>Careers</span>
        </a>
        <div class="apply-summary-main">
            <span class="apply-summary-eyebrow">Applying for</span>
            <h1 class="apply-summary-title"><?php echo htmlspecialchars($job['title']); ?></h1>
        </div>
        <div class="apply-summary-meta">
            <?php if (!empty($job['department'])): ?><span class="apply-pill"><?php echo htmlspecialchars($job['department']); ?></span><?php endif; ?>
            <?php if (!empty($job['location'])): ?><span class="apply-pill apply-pill-soft"><?php echo htmlspecialchars($job['location']); ?></span><?php endif; ?>
            <?php if (!empty($job['employment_type'])): ?><span class="apply-pill apply-pill-soft"><?php echo htmlspecialchars($job['employment_type']); ?></span><?php endif; ?>
            <?php if (!empty($job['work_mode'])): ?><span class="apply-pill apply-pill-soft"><?php echo htmlspecialchars($job['work_mode']); ?></span><?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors['_global'])): ?>
        <div class="apply-alert"><?php echo htmlspecialchars($errors['_global']); ?></div>
    <?php endif; ?>

    <div class="apply-restore" id="applyRestore" hidden>
        We restored your saved progress. <strong>Please re-attach your resume and any documents</strong> before submitting.
        <button type="button" id="applyClearBtn" class="apply-restore-clear">Start Over</button>
    </div>

    <!-- Progress indicator -->
    <div class="apply-progress" id="applyProgress">
        <div class="apply-progress-head">
            <span class="apply-step-count">Step <span id="apStepNum">1</span> of 7</span>
            <span class="apply-step-name" id="apStepName">Resume Upload</span>
        </div>
        <div class="apply-progress-track"><div class="apply-progress-fill" id="apProgressFill"></div></div>
        <ol class="apply-steps" id="applySteps">
            <?php
            $ap_labels = ['Resume', 'Personal', 'Professional', 'Education', 'Skills', 'Questions', 'Review'];
            foreach ($ap_labels as $i => $label):
            ?>
            <li class="apply-step-item<?php echo $i === 0 ? ' is-active' : ''; ?>" data-step="<?php echo $i + 1; ?>">
                <span class="apply-step-dot"><span class="dot-num"><?php echo $i + 1; ?></span><span class="dot-check">&#10003;</span></span>
                <span class="apply-step-label"><?php echo htmlspecialchars($label); ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <form class="apply-form" id="applyForm" method="POST" action="" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="job_id" value="<?php echo (int) $job_id; ?>">
        <input type="hidden" name="skills" id="apSkillsInput" value="<?php echo av($old, 'skills'); ?>">
        <input type="hidden" name="willing_to_relocate" id="apRelocateInput" value="<?php echo av($old, 'willing_to_relocate', ''); ?>">

        <!-- ===== STEP 1: Resume Upload & AI Preparation ===== -->
        <section class="apply-panel apply-card is-active" data-panel="1">
            <h2>Resume Upload</h2>
            <p class="apply-card-sub">Start by uploading your resume. PDF, DOC or DOCX &middot; up to 5&nbsp;MB.</p>

            <div class="apply-ai-note">
                <span class="apply-ai-badge">Coming soon</span>
                <span>Automatic Resume Parsing will be available soon — it will read your resume and pre-fill the next steps for you. For now, please upload your resume and continue filling the form.</span>
            </div>

            <div class="apply-field">
                <label for="resume">Resume <span class="req">*</span></label>
                <div class="apply-file apply-file--drop" data-file-for="resume">
                    <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" data-required-file>
                    <div class="apply-file-face">
                        <span class="apply-file-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        </span>
                        <span class="apply-file-text"><strong>Drag &amp; drop your resume here</strong><span>or click to browse — PDF, DOC or DOCX</span></span>
                    </div>
                </div>
                <small class="apply-err" data-error-for="resume"><?php echo htmlspecialchars($errors['resume'] ?? ''); ?></small>
            </div>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="cover_letter_file">Cover Letter <span class="opt">(optional)</span></label>
                    <div class="apply-file" data-file-for="cover_letter_file">
                        <input type="file" id="cover_letter_file" name="cover_letter_file" accept=".pdf,.doc,.docx">
                        <div class="apply-file-face">
                            <span class="apply-file-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            </span>
                            <span class="apply-file-text"><strong>Click to upload (optional)</strong><span>PDF, DOC or DOCX</span></span>
                        </div>
                    </div>
                    <small class="apply-err" data-error-for="cover_letter_file"><?php echo htmlspecialchars($errors['cover_letter_file'] ?? ''); ?></small>
                </div>
                <div class="apply-field">
                    <label for="portfolio_url">Portfolio / Publications URL <span class="opt">(optional)</span></label>
                    <input type="url" id="portfolio_url" name="portfolio_url" value="<?php echo av($old, 'portfolio_url'); ?>" maxlength="255" placeholder="https://...">
                    <small class="apply-err" data-error-for="portfolio_url"><?php echo htmlspecialchars($errors['portfolio_url'] ?? ''); ?></small>
                </div>
            </div>
            <p class="apply-note">For your security, files are never stored in your browser. If you refresh this page, please re-select them.</p>
        </section>

        <!-- ===== STEP 2: Personal Information ===== -->
        <section class="apply-panel apply-card" data-panel="2">
            <h2>Personal Information</h2>
            <p class="apply-card-sub">Tell us how to reach you. No account required.</p>

            <div class="apply-field">
                <label for="full_name">Full Name <span class="req">*</span></label>
                <input type="text" id="full_name" name="full_name" value="<?php echo av($old, 'full_name'); ?>" data-required maxlength="120" placeholder="Your full name">
                <small class="apply-err" data-error-for="full_name"><?php echo htmlspecialchars($errors['full_name'] ?? ''); ?></small>
            </div>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="email">Email Address <span class="req">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo av($old, 'email'); ?>" data-required maxlength="190" placeholder="you@example.com">
                    <small class="apply-err" data-error-for="email"><?php echo htmlspecialchars($errors['email'] ?? ''); ?></small>
                </div>
                <div class="apply-field">
                    <label for="mobile">Mobile Number <span class="req">*</span></label>
                    <input type="text" id="mobile" name="mobile" value="<?php echo av($old, 'mobile'); ?>" data-required maxlength="20" placeholder="+91 90000 00000">
                    <small class="apply-err" data-error-for="mobile"><?php echo htmlspecialchars($errors['mobile'] ?? ''); ?></small>
                </div>
            </div>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="current_location">Current Location <span class="req">*</span></label>
                    <input type="text" id="current_location" name="current_location" value="<?php echo av($old, 'current_location'); ?>" data-required maxlength="160" placeholder="City, State, Country">
                    <small class="apply-err" data-error-for="current_location"><?php echo htmlspecialchars($errors['current_location'] ?? ''); ?></small>
                </div>
                <div class="apply-field">
                    <label for="linkedin_profile">LinkedIn Profile <span class="opt">(optional)</span></label>
                    <input type="url" id="linkedin_profile" name="linkedin_profile" value="<?php echo av($old, 'linkedin_profile'); ?>" maxlength="255" placeholder="https://linkedin.com/in/you">
                    <small class="apply-err" data-error-for="linkedin_profile"><?php echo htmlspecialchars($errors['linkedin_profile'] ?? ''); ?></small>
                </div>
            </div>
        </section>

        <!-- ===== STEP 3: Professional Information ===== -->
        <section class="apply-panel apply-card" data-panel="3">
            <h2>Professional Information</h2>
            <p class="apply-card-sub">Your experience and current role.</p>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="total_experience">Total Experience (years) <span class="req">*</span></label>
                    <input type="number" id="total_experience" name="total_experience" value="<?php echo av($old, 'total_experience'); ?>" data-required min="0" max="60" step="0.5" placeholder="e.g. 3.5">
                    <small class="apply-err" data-error-for="total_experience"><?php echo htmlspecialchars($errors['total_experience'] ?? ''); ?></small>
                </div>
                <div class="apply-field">
                    <label for="relevant_experience">Relevant Experience (years) <span class="req">*</span></label>
                    <input type="number" id="relevant_experience" name="relevant_experience" value="<?php echo av($old, 'relevant_experience'); ?>" data-required min="0" max="60" step="0.5" placeholder="e.g. 2">
                    <small class="apply-err" data-error-for="relevant_experience"><?php echo htmlspecialchars($errors['relevant_experience'] ?? ''); ?></small>
                </div>
            </div>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="current_company">Current Company <span class="opt">(optional)</span></label>
                    <input type="text" id="current_company" name="current_company" value="<?php echo av($old, 'current_company'); ?>" maxlength="160" placeholder="Company name">
                </div>
                <div class="apply-field">
                    <label for="current_designation">Current Designation <span class="opt">(optional)</span></label>
                    <input type="text" id="current_designation" name="current_designation" value="<?php echo av($old, 'current_designation'); ?>" maxlength="160" placeholder="Job title">
                </div>
            </div>

            <div class="apply-grid-3">
                <div class="apply-field">
                    <label for="ctc_currency">Currency</label>
                    <select id="ctc_currency" name="ctc_currency">
                        <?php foreach ($currencies as $c): $sel = (($old['ctc_currency'] ?? 'INR') === $c) ? 'selected' : ''; ?>
                            <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="apply-field">
                    <label for="current_ctc">Current CTC <span class="opt">(optional)</span></label>
                    <input type="number" id="current_ctc" name="current_ctc" value="<?php echo av($old, 'current_ctc'); ?>" min="0" step="1000" placeholder="e.g. 800000">
                    <small class="apply-err" data-error-for="current_ctc"><?php echo htmlspecialchars($errors['current_ctc'] ?? ''); ?></small>
                </div>
                <div class="apply-field">
                    <label for="expected_ctc">Expected CTC <span class="opt">(optional)</span></label>
                    <input type="number" id="expected_ctc" name="expected_ctc" value="<?php echo av($old, 'expected_ctc'); ?>" min="0" step="1000" placeholder="e.g. 1200000">
                    <small class="apply-err" data-error-for="expected_ctc"><?php echo htmlspecialchars($errors['expected_ctc'] ?? ''); ?></small>
                </div>
            </div>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="employment_status">Current Employment Status <span class="req">*</span></label>
                    <select id="employment_status" name="employment_status" data-required>
                        <option value="">— Select —</option>
                        <?php foreach ($emp_statuses as $s): $sel = (($old['employment_status'] ?? '') === $s) ? 'selected' : ''; ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="apply-err" data-error-for="employment_status"><?php echo htmlspecialchars($errors['employment_status'] ?? ''); ?></small>
                </div>
                <div class="apply-field">
                    <label for="notice_period">Notice Period <span class="req">*</span></label>
                    <input type="text" id="notice_period" name="notice_period" value="<?php echo av($old, 'notice_period'); ?>" data-required maxlength="60" placeholder="e.g. 30 days / Immediate">
                    <small class="apply-err" data-error-for="notice_period"><?php echo htmlspecialchars($errors['notice_period'] ?? ''); ?></small>
                </div>
            </div>
        </section>

        <!-- ===== STEP 4: Education ===== -->
        <section class="apply-panel apply-card" data-panel="4">
            <h2>Education</h2>
            <p class="apply-card-sub">Your highest qualification.</p>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="qualification">Highest Qualification <span class="req">*</span></label>
                    <select id="qualification" name="qualification" data-required>
                        <option value="">— Select —</option>
                        <?php foreach ($qualifications as $q): $sel = (($old['qualification'] ?? '') === $q) ? 'selected' : ''; ?>
                            <option value="<?php echo htmlspecialchars($q); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($q); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="apply-err" data-error-for="qualification"><?php echo htmlspecialchars($errors['qualification'] ?? ''); ?></small>
                </div>
                <div class="apply-field">
                    <label for="specialization">Specialization <span class="opt">(optional)</span></label>
                    <input type="text" id="specialization" name="specialization" value="<?php echo av($old, 'specialization'); ?>" maxlength="120" placeholder="e.g. Biostatistics">
                </div>
            </div>

            <div class="apply-grid-2">
                <div class="apply-field">
                    <label for="university_college">University / College <span class="opt">(optional)</span></label>
                    <input type="text" id="university_college" name="university_college" value="<?php echo av($old, 'university_college'); ?>" maxlength="160" placeholder="Institution name">
                </div>
                <div class="apply-field">
                    <label for="graduation_year">Graduation Year <span class="opt">(optional)</span></label>
                    <input type="number" id="graduation_year" name="graduation_year" value="<?php echo av($old, 'graduation_year'); ?>" min="1950" max="<?php echo $max_grad_year; ?>" step="1" placeholder="e.g. 2021">
                    <small class="apply-err" data-error-for="graduation_year"><?php echo htmlspecialchars($errors['graduation_year'] ?? ''); ?></small>
                </div>
            </div>
        </section>

        <!-- ===== STEP 5: Skills ===== -->
        <section class="apply-panel apply-card" data-panel="5">
            <h2>Skills</h2>
            <p class="apply-card-sub">Search and select the skills that match your expertise.</p>

            <div class="apply-field">
                <label>Your Skills</label>
                <div class="apply-skillpicker" id="applySkillPicker">
                    <div class="apply-skill-searchwrap">
                        <input type="text" class="apply-skill-search" id="apSkillSearch" placeholder="Search skills…" aria-label="Search skills" autocomplete="off">
                        <div class="apply-skill-dropdown" id="apSkillDropdown" role="listbox"></div>
                    </div>
                    <div class="apply-skill-chips" id="apSkillChips" aria-live="polite"></div>
                    <small class="apply-hint">Click a skill to add it. Click the &times; on a chip to remove it.</small>
                </div>
            </div>
        </section>

        <!-- ===== STEP 6: Additional Questions ===== -->
        <section class="apply-panel apply-card" data-panel="6">
            <h2>Additional Questions</h2>
            <p class="apply-card-sub">Help us understand your motivation.</p>

            <div class="apply-field">
                <label for="why_interested">Why are you interested in this position? <span class="opt">(optional)</span></label>
                <textarea id="why_interested" name="why_interested" rows="4" maxlength="2000" placeholder="Share what draws you to this role…"><?php echo htmlspecialchars((string) ($old['why_interested'] ?? '')); ?></textarea>
                <small class="apply-count" data-count-for="why_interested"></small>
            </div>

            <div class="apply-field">
                <label for="why_cpvia">Why do you want to join CPVIA? <span class="opt">(optional)</span></label>
                <textarea id="why_cpvia" name="why_cpvia" rows="4" maxlength="2000" placeholder="Tell us why CPVIA…"><?php echo htmlspecialchars((string) ($old['why_cpvia'] ?? '')); ?></textarea>
                <small class="apply-count" data-count-for="why_cpvia"></small>
            </div>

            <div class="apply-field">
                <label>Are you willing to relocate? <span class="req">*</span></label>
                <div class="apply-radios" id="apRelocateGroup">
                    <label class="apply-radio"><input type="radio" name="willing_to_relocate_ui" value="1"> <span>Yes</span></label>
                    <label class="apply-radio"><input type="radio" name="willing_to_relocate_ui" value="0"> <span>No</span></label>
                </div>
                <small class="apply-err" data-error-for="willing_to_relocate"><?php echo htmlspecialchars($errors['willing_to_relocate'] ?? ''); ?></small>
            </div>
        </section>

        <!-- ===== STEP 7: Review & Declaration ===== -->
        <section class="apply-panel apply-card" data-panel="7">
            <h2>Review &amp; Submit</h2>
            <p class="apply-card-sub">Please review your details before submitting.</p>

            <div class="apply-review" id="applyReview"><!-- filled by JS --></div>

            <div class="apply-declarations">
                <label class="apply-check">
                    <input type="checkbox" name="declaration_accurate" id="declaration_accurate" value="1" <?php echo !empty($old['declaration_accurate']) ? 'checked' : ''; ?>>
                    <span>I confirm that the information provided is accurate.</span>
                </label>
                <small class="apply-err" data-error-for="declaration_accurate"><?php echo htmlspecialchars($errors['declaration_accurate'] ?? ''); ?></small>

                <label class="apply-check">
                    <input type="checkbox" name="consent_data_storage" id="consent_data_storage" value="1" <?php echo !empty($old['consent_data_storage']) ? 'checked' : ''; ?>>
                    <span>I agree to CPVIA storing my information for recruitment purposes.</span>
                </label>
                <small class="apply-err" data-error-for="consent_data_storage"><?php echo htmlspecialchars($errors['consent_data_storage'] ?? ''); ?></small>
            </div>
        </section>

        <!-- ===== Navigation ===== -->
        <div class="apply-nav">
            <button type="button" class="apply-btn apply-btn-ghost" id="apPrev">Previous</button>
            <div class="apply-nav-right">
                <button type="button" class="apply-btn apply-btn-primary" id="apNext">Next</button>
                <button type="submit" class="apply-btn apply-btn-primary" id="apSubmit" style="display:none;">Submit Application</button>
            </div>
        </div>
    </form>
<?php endif; ?>
</div>

<script>
    window.CPVIA_APPLY = {
        skills: <?php echo $skills_json ?: '[]'; ?>,
        jobId: <?php echo (int) $job_id; ?>,
        errorFields: <?php echo $error_fields_json ?: '[]'; ?>,
        errorStep: <?php echo (int) $error_step; ?>,
        submitted: <?php echo $success ? 'true' : 'false'; ?>
    };
</script>
<script src="assets/js/apply_wizard.js"></script>

<?php include __DIR__ . '/footer.php'; ?>
