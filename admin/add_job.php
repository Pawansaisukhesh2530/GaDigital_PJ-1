<?php
/**
 * Add Job — 8-Step Job Posting Wizard
 * -----------------------------------------------------------------------------
 * Uses the shared wizard partial (partials/job_wizard_form.php), shared helpers
 * (job_helpers.php), CSS and JS, so Add Job and Edit Job stay consistent.
 * Creates a new row in `jobs` and its `job_skills`. Supports Save Draft/Publish.
 * -----------------------------------------------------------------------------
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/job_helpers.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$error = '';

$opts = cpvia_job_option_lists();
$values = cpvia_default_job_values();
$selected_required = [];
$selected_preferred = [];

$pdo = cpvia_db($db_file);
$all_skills = cpvia_fetch_skills($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = ($_POST['action'] ?? '') === 'publish' ? 'publish' : 'draft';

    $collected = cpvia_collect_job_post($_POST);
    $values = $collected['values'];
    $selected_required = $collected['required'];
    $selected_preferred = $collected['preferred'];

    if (!cpvia_csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Your session has expired. Please refresh and try again.';
    } else {
        $error = cpvia_validate_job($pdo, $values, $action, null);
    }

    if ($error === '') {
        $status = $action === 'publish' ? cpvia_job_status('published') : cpvia_job_status('draft');
        $location = cpvia_compose_location($values['city'], $values['state'], $values['country'], $values['office_location']);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO jobs (
                    title, department, location, employment_type, description, requirements, status,
                    job_code, work_mode, number_of_openings, hiring_priority,
                    country, state, city, office_location, remote_available,
                    min_experience, max_experience, minimum_qualification, degree, specialization,
                    salary_type, min_salary, max_salary, currency,
                    responsibilities, benefits, preferred_notice_period, gender_preference,
                    minimum_age, maximum_age, updated_at,
                    submission_mode, recipient_emails
                ) VALUES (
                    :title, :department, :location, :employment_type, :description, :requirements, :status,
                    :job_code, :work_mode, :number_of_openings, :hiring_priority,
                    :country, :state, :city, :office_location, :remote_available,
                    :min_experience, :max_experience, :minimum_qualification, :degree, :specialization,
                    :salary_type, :min_salary, :max_salary, :currency,
                    :responsibilities, :benefits, :preferred_notice_period, :gender_preference,
                    :minimum_age, :maximum_age, :updated_at,
                    :submission_mode, :recipient_emails
                )
            ");
            $stmt->execute(cpvia_job_param_map($values, $location, $status, date('Y-m-d H:i:s')));

            $job_id = (int) $pdo->lastInsertId();
            cpvia_replace_job_skills($pdo, $job_id, $selected_required, 'required');
            cpvia_replace_job_skills($pdo, $job_id, $selected_preferred, 'preferred');

            $pdo->commit();

            header('Location: jobs.php?success=' . ($action === 'publish' ? 'added' : 'draft'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Add Job';
$active_nav = 'add_job';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Jobs', 'url' => 'jobs.php'],
    ['label' => 'Add Job', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';

// Data for the shared wizard partial / JS.
$skills_json = json_encode($all_skills, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$required_json = json_encode(array_values($selected_required));
$preferred_json = json_encode(array_values($selected_preferred));

// Wizard mode configuration (Add).
$wizard_is_edit = false;
$wizard_form_action = '';
$wizard_publish_label = 'Publish Job';
$wizard_draft_label = 'Save Draft';
$wizard_context = null;
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php include __DIR__ . '/partials/job_wizard_form.php'; ?>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
