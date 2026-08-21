<?php
/**
 * Single-Page Job Editor - 4 consolidated sections with independent save.
 * Requirements: 7.1, 7.5, 7.7
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/job_helpers.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';

// --- Resolve job ID ---
$job_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($job_id <= 0) {
    header('Location: jobs.php');
    exit;
}

$pdo = cpvia_db($db_file);

// --- Fetch the job (prepared statement) ---
try {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    header('Location: jobs.php');
    exit;
}
if (!$job) {
    header('Location: jobs.php');
    exit;
}

// --- Build values array from job row ---
$values = cpvia_job_row_to_values($job);
$opts = cpvia_job_option_lists();
$all_skills = cpvia_fetch_skills($pdo);

// --- Load current skills for this job ---
$selected_required = [];
$selected_preferred = [];
try {
    $sk = $pdo->prepare("SELECT skill_id, skill_type FROM job_skills WHERE job_id = ?");
    $sk->execute([$job_id]);
    foreach ($sk->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int) $row['skill_id'];
        if ($row['skill_type'] === 'preferred') {
            $selected_preferred[] = $sid;
        } else {
            $selected_required[] = $sid;
        }
    }
} catch (Throwable $e) {
    $selected_required = [];
    $selected_preferred = [];
}

// --- Determine which section to pre-expand ---
$expand_section = isset($_GET['section']) ? (int) $_GET['section'] : 0;
if ($expand_section < 1 || $expand_section > 4) {
    $expand_section = 1; // default to first section
}

// --- Flash message and session errors ---
$flash = isset($_GET['flash']) ? trim($_GET['flash']) : '';
$section_error = $_SESSION['section_error'] ?? '';
$old_values = $_SESSION['section_values'] ?? [];
unset($_SESSION['section_error'], $_SESSION['section_values']);
$errors = $section_error !== '' ? [$section_error] : [];

// If we have old values from a failed save, overlay them on current values
if (!empty($old_values)) {
    foreach ($old_values as $k => $v) {
        if (array_key_exists($k, $values)) {
            $values[$k] = $v;
        }
    }
}

// --- Option list shortcuts ---
$employment_types = $opts['employment_types'];
$work_modes = $opts['work_modes'];
$priorities = $opts['priorities'];
$salary_types = $opts['salary_types'];
$currencies = $opts['currencies'];
$qualifications = $opts['qualifications'];
$genders = $opts['genders'];

// --- Skills JSON ---
$skills_json = json_encode($all_skills, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$required_json = json_encode(array_values(array_unique($selected_required)));
$preferred_json = json_encode(array_values(array_unique($selected_preferred)));

// --- CSRF token ---
$csrf = cpvia_csrf_token();

// --- Layout ---
$page_title = 'Edit Job - ' . ($values['title'] !== '' ? $values['title'] : 'Untitled');
$active_nav = 'jobs';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Jobs', 'url' => 'jobs.php'],
    ['label' => 'Edit Job', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';

function esc(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}
?>

<?php if ($flash === 'saved'): ?>
    <div class="alert alert-success">Changes saved successfully.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="edit-single-container">

<!-- ══════════════ SECTION 1: Job Overview ══════════════ -->
<details class="edit-section" data-section="1" <?php echo $expand_section === 1 ? 'open' : ''; ?>>
    <summary class="edit-section-header">1. Job Overview</summary>
    <form method="POST" action="save_job_section.php?id=<?php echo $job_id; ?>" class="edit-section-form" data-section="1">
        <input type="hidden" name="section" value="1">
        <input type="hidden" name="id" value="<?php echo $job_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">

        <div class="form-group">
            <label for="s1_title">Job Title <span class="req">*</span></label>
            <input type="text" id="s1_title" name="title" value="<?php echo esc($values['title']); ?>" placeholder="e.g. Senior Clinical SAS Programmer">
        </div>

        <div class="wiz-grid-3">
            <div class="form-group">
                <label for="s1_department">Department</label>
                <input type="text" id="s1_department" name="department" value="<?php echo esc($values['department']); ?>" placeholder="e.g. Biometrics">
            </div>
            <div class="form-group">
                <label for="s1_job_code">Job Code</label>
                <input type="text" id="s1_job_code" name="job_code" value="<?php echo esc($values['job_code']); ?>" placeholder="e.g. CPV-2026-014">
            </div>
            <div class="form-group">
                <label for="s1_employment_type">Employment Type</label>
                <select id="s1_employment_type" name="employment_type">
                    <?php foreach ($employment_types as $t): ?>
                        <option value="<?php echo esc($t); ?>" <?php echo $values['employment_type'] === $t ? 'selected' : ''; ?>><?php echo esc($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="wiz-grid-3">
            <div class="form-group">
                <label for="s1_work_mode">Work Mode</label>
                <select id="s1_work_mode" name="work_mode">
                    <?php foreach ($work_modes as $t): ?>
                        <option value="<?php echo esc($t); ?>" <?php echo $values['work_mode'] === $t ? 'selected' : ''; ?>><?php echo esc($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="s1_openings">Number of Openings</label>
                <input type="number" id="s1_openings" name="number_of_openings" min="1" step="1" value="<?php echo esc($values['number_of_openings']); ?>">
            </div>
            <div class="form-group">
                <label for="s1_priority">Hiring Priority</label>
                <select id="s1_priority" name="hiring_priority">
                    <?php foreach ($priorities as $t): ?>
                        <option value="<?php echo esc($t); ?>" <?php echo $values['hiring_priority'] === $t ? 'selected' : ''; ?>><?php echo esc($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h4 class="edit-subsection-title">Location</h4>

        <div class="wiz-grid-3">
            <div class="form-group">
                <label for="s1_country">Country</label>
                <input type="text" id="s1_country" name="country" value="<?php echo esc($values['country']); ?>" placeholder="e.g. India">
            </div>
            <div class="form-group">
                <label for="s1_state">State</label>
                <input type="text" id="s1_state" name="state" value="<?php echo esc($values['state']); ?>" placeholder="e.g. Telangana">
            </div>
            <div class="form-group">
                <label for="s1_city">City</label>
                <input type="text" id="s1_city" name="city" value="<?php echo esc($values['city']); ?>" placeholder="e.g. Hyderabad">
            </div>
        </div>

        <div class="wiz-grid-2">
            <div class="form-group">
                <label for="s1_office">Office Location</label>
                <input type="text" id="s1_office" name="office_location" value="<?php echo esc($values['office_location']); ?>" placeholder="e.g. HITEC City Campus">
            </div>
            <div class="form-group form-group-checkbox">
                <label>
                    <input type="checkbox" name="remote_available" value="1" <?php echo $values['remote_available'] ? 'checked' : ''; ?>>
                    Remote Available
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Section</button>
        </div>
    </form>
</details>

<!-- ══════════════ SECTION 2: Requirements & Qualifications ══════════════ -->
<details class="edit-section" data-section="2" <?php echo $expand_section === 2 ? 'open' : ''; ?>>
    <summary class="edit-section-header">2. Requirements &amp; Qualifications</summary>
    <form method="POST" action="save_job_section.php?id=<?php echo $job_id; ?>" class="edit-section-form" data-section="2">
        <input type="hidden" name="section" value="2">
        <input type="hidden" name="id" value="<?php echo $job_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">

        <h4 class="edit-subsection-title">Experience</h4>
        <div class="wiz-grid-2">
            <div class="form-group">
                <label for="s2_min_exp">Minimum Experience (years)</label>
                <input type="number" id="s2_min_exp" name="min_experience" min="0" step="1" value="<?php echo esc($values['min_experience']); ?>">
            </div>
            <div class="form-group">
                <label for="s2_max_exp">Maximum Experience (years)</label>
                <input type="number" id="s2_max_exp" name="max_experience" min="0" step="1" value="<?php echo esc($values['max_experience']); ?>">
            </div>
        </div>

        <h4 class="edit-subsection-title">Education</h4>
        <div class="wiz-grid-3">
            <div class="form-group">
                <label for="s2_qualification">Minimum Qualification</label>
                <select id="s2_qualification" name="minimum_qualification">
                    <option value="">-- Select --</option>
                    <?php foreach ($qualifications as $q): ?>
                        <option value="<?php echo esc($q); ?>" <?php echo $values['minimum_qualification'] === $q ? 'selected' : ''; ?>><?php echo esc($q); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="s2_degree">Degree</label>
                <input type="text" id="s2_degree" name="degree" value="<?php echo esc($values['degree']); ?>" placeholder="e.g. B.Pharm, M.Sc Statistics">
            </div>
            <div class="form-group">
                <label for="s2_specialization">Specialization</label>
                <input type="text" id="s2_specialization" name="specialization" value="<?php echo esc($values['specialization']); ?>" placeholder="e.g. Clinical Data Management">
            </div>
        </div>

        <h4 class="edit-subsection-title">Salary</h4>
        <div class="wiz-grid-4">
            <div class="form-group">
                <label for="s2_salary_type">Salary Type</label>
                <select id="s2_salary_type" name="salary_type">
                    <?php foreach ($salary_types as $t): ?>
                        <option value="<?php echo esc($t); ?>" <?php echo $values['salary_type'] === $t ? 'selected' : ''; ?>><?php echo esc($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="s2_min_salary">Min Salary</label>
                <input type="number" id="s2_min_salary" name="min_salary" min="0" step="1" value="<?php echo esc($values['min_salary']); ?>">
            </div>
            <div class="form-group">
                <label for="s2_max_salary">Max Salary</label>
                <input type="number" id="s2_max_salary" name="max_salary" min="0" step="1" value="<?php echo esc($values['max_salary']); ?>">
            </div>
            <div class="form-group">
                <label for="s2_currency">Currency</label>
                <select id="s2_currency" name="currency">
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?php echo esc($c); ?>" <?php echo $values['currency'] === $c ? 'selected' : ''; ?>><?php echo esc($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h4 class="edit-subsection-title">Skills</h4>
        <div class="wiz-grid-2">
            <div class="form-group">
                <label>Required Skills</label>
                <input type="hidden" name="required_skills" id="s2_requiredSkillsInput" value="<?php echo esc(implode(',', $selected_required)); ?>">
                <div class="skill-picker" data-skill-target="s2_requiredSkillsInput" data-skill-type="required">
                    <div class="skill-search-wrap">
                        <input type="text" class="skill-search" placeholder="Search skills to add as required…" aria-label="Search required skills" autocomplete="off">
                        <div class="skill-dropdown" role="listbox"></div>
                    </div>
                    <div class="skill-chips" aria-live="polite"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Preferred Skills</label>
                <input type="hidden" name="preferred_skills" id="s2_preferredSkillsInput" value="<?php echo esc(implode(',', $selected_preferred)); ?>">
                <div class="skill-picker" data-skill-target="s2_preferredSkillsInput" data-skill-type="preferred">
                    <div class="skill-search-wrap">
                        <input type="text" class="skill-search" placeholder="Search skills to add as preferred…" aria-label="Search preferred skills" autocomplete="off">
                        <div class="skill-dropdown" role="listbox"></div>
                    </div>
                    <div class="skill-chips" aria-live="polite"></div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Section</button>
        </div>
    </form>
</details>

<!-- ══════════════ SECTION 3: Job Description ══════════════ -->
<details class="edit-section" data-section="3" <?php echo $expand_section === 3 ? 'open' : ''; ?>>
    <summary class="edit-section-header">3. Job Description</summary>
    <form method="POST" action="save_job_section.php?id=<?php echo $job_id; ?>" class="edit-section-form" data-section="3">
        <input type="hidden" name="section" value="3">
        <input type="hidden" name="id" value="<?php echo $job_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">

        <div class="form-group">
            <label for="s3_description">Description</label>
            <textarea id="s3_description" name="description" class="richtext-editor" rows="10"><?php echo htmlspecialchars($values['description']); ?></textarea>
        </div>

        <div class="form-group">
            <label for="s3_responsibilities">Responsibilities</label>
            <textarea id="s3_responsibilities" name="responsibilities" class="richtext-editor" rows="8"><?php echo htmlspecialchars($values['responsibilities']); ?></textarea>
        </div>

        <div class="form-group">
            <label for="s3_requirements">Requirements</label>
            <textarea id="s3_requirements" name="requirements" class="richtext-editor" rows="8"><?php echo htmlspecialchars($values['requirements']); ?></textarea>
        </div>

        <div class="form-group">
            <label for="s3_benefits">Benefits</label>
            <textarea id="s3_benefits" name="benefits" class="richtext-editor" rows="6"><?php echo htmlspecialchars($values['benefits']); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Section</button>
        </div>
    </form>
</details>

<!-- ══════════════ SECTION 4: Preferences & Delivery ══════════════ -->
<details class="edit-section" data-section="4" <?php echo $expand_section === 4 ? 'open' : ''; ?>>
    <summary class="edit-section-header">4. Preferences &amp; Delivery</summary>
    <form method="POST" action="save_job_section.php?id=<?php echo $job_id; ?>" class="edit-section-form" data-section="4">
        <input type="hidden" name="section" value="4">
        <input type="hidden" name="id" value="<?php echo $job_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">

        <h4 class="edit-subsection-title">Candidate Preferences</h4>
        <div class="wiz-grid-4">
            <div class="form-group">
                <label for="s4_notice_period">Preferred Notice Period</label>
                <input type="text" id="s4_notice_period" name="preferred_notice_period" value="<?php echo esc($values['preferred_notice_period']); ?>" placeholder="e.g. 30 days">
            </div>
            <div class="form-group">
                <label for="s4_gender">Gender Preference</label>
                <select id="s4_gender" name="gender_preference">
                    <?php foreach ($genders as $g): ?>
                        <option value="<?php echo esc($g); ?>" <?php echo $values['gender_preference'] === $g ? 'selected' : ''; ?>><?php echo esc($g); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="s4_min_age">Minimum Age</label>
                <input type="number" id="s4_min_age" name="minimum_age" min="0" step="1" value="<?php echo esc($values['minimum_age']); ?>">
            </div>
            <div class="form-group">
                <label for="s4_max_age">Maximum Age</label>
                <input type="number" id="s4_max_age" name="maximum_age" min="0" step="1" value="<?php echo esc($values['maximum_age']); ?>">
            </div>
        </div>

        <h4 class="edit-subsection-title">Application Delivery</h4>
        <div class="wiz-grid-2">
            <div class="form-group">
                <label for="s4_submission_mode">Submission Mode</label>
                <select id="s4_submission_mode" name="submission_mode">
                    <?php foreach (cpvia_submission_modes() as $mode): ?>
                        <option value="<?php echo esc($mode); ?>" <?php echo $values['submission_mode'] === $mode ? 'selected' : ''; ?>><?php echo esc(str_replace('_', ' ', $mode)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="s4_emails">Recipient Emails</label>
                <input type="text" id="s4_emails" name="recipient_emails" value="<?php echo esc($values['recipient_emails']); ?>" placeholder="e.g. hr@cpvia.com, careers@cpvia.com">
                <small class="field-hint">Comma-separated. Required when mode includes email delivery.</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Section</button>
        </div>
    </form>
</details>

</div><!-- /.edit-single-container -->

<script>var cpviaSkillsData = <?php echo $skills_json; ?>;</script>
<script>var cpviaRequiredSelected = <?php echo $required_json; ?>;</script>
<script>var cpviaPreferredSelected = <?php echo $preferred_json; ?>;</script>
<script src="assets/edit_single.js"></script>
<script src="assets/skill_picker.js"></script>
<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
