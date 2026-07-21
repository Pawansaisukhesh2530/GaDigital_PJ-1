<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/job_helpers.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$error = '';

$job_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
if ($job_id <= 0) {
    header('Location: jobs.php');
    exit;
}

$pdo = cpvia_db($db_file);
$opts = cpvia_job_option_lists();
$all_skills = cpvia_fetch_skills($pdo);

$values = cpvia_default_job_values();
$selected_required = [];
$selected_preferred = [];

// Load the existing job (prepared statement). Redirect if it does not exist.
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

$current_status = (string) ($job['status'] ?? cpvia_job_status('draft'));
$draft_status = cpvia_job_status('draft');
$published_status = cpvia_job_status('published');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = ($_POST['action'] ?? '') === 'publish' ? 'publish' : 'draft';

    $collected = cpvia_collect_job_post($_POST);
    $values = $collected['values'];
    $selected_required = $collected['required'];
    $selected_preferred = $collected['preferred'];

    if (!cpvia_csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Your session has expired. Please refresh and try again.';
    } else {
        // Exclude the current job from the Job Code uniqueness check.
        $error = cpvia_validate_job($pdo, $values, $action, $job_id);
    }

    if ($error === '') {
        
        if ($action === 'draft') {
            $status = $draft_status;
        } elseif ($current_status === $draft_status) {
            $status = $published_status;
        } else {
            $status = $current_status !== '' ? $current_status : $published_status;
        }

        $location = cpvia_compose_location($values['city'], $values['state'], $values['country'], $values['office_location']);
        if ($location === '' && !empty($job['location'])) {
            $location = (string) $job['location'];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE jobs SET
                    title = :title, department = :department, location = :location,
                    employment_type = :employment_type, description = :description,
                    requirements = :requirements, status = :status,
                    job_code = :job_code, work_mode = :work_mode, number_of_openings = :number_of_openings,
                    hiring_priority = :hiring_priority, country = :country, state = :state, city = :city,
                    office_location = :office_location, remote_available = :remote_available,
                    min_experience = :min_experience, max_experience = :max_experience,
                    minimum_qualification = :minimum_qualification, degree = :degree, specialization = :specialization,
                    salary_type = :salary_type, min_salary = :min_salary, max_salary = :max_salary, currency = :currency,
                    responsibilities = :responsibilities, benefits = :benefits,
                    preferred_notice_period = :preferred_notice_period, gender_preference = :gender_preference,
                    minimum_age = :minimum_age, maximum_age = :maximum_age, updated_at = :updated_at
                WHERE id = :id
            ");

            $params = cpvia_job_param_map($values, $location, $status, date('Y-m-d H:i:s'));
            $params[':id'] = $job_id;
            $stmt->execute($params);

            // Replace this job's skill relationships only (delete-per-type + insert).
            cpvia_replace_job_skills($pdo, $job_id, $selected_required, 'required');
            cpvia_replace_job_skills($pdo, $job_id, $selected_preferred, 'preferred');

            $pdo->commit();

            header('Location: jobs.php?success=updated');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Database error: ' . $e->getMessage();
        }
    }
} else {
    $values = cpvia_job_row_to_values($job);

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
        // Non-fatal: show the form without preselected skills.
        $selected_required = [];
        $selected_preferred = [];
    }
}

$page_title = 'Edit Job';
$active_nav = 'jobs';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Jobs', 'url' => 'jobs.php'],
    ['label' => 'Edit Job', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';

// Data for the shared wizard partial / JS.
$skills_json = json_encode($all_skills, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$required_json = json_encode(array_values(array_unique($selected_required)));
$preferred_json = json_encode(array_values(array_unique($selected_preferred)));

// Wizard mode configuration (Edit).
$is_current_draft = ($current_status === $draft_status);
$wizard_is_edit = true;
$wizard_job_id = $job_id;
$wizard_form_action = 'edit_job.php?id=' . $job_id;
$wizard_publish_label = $is_current_draft ? 'Publish Job' : 'Update Job';
$wizard_draft_label = $is_current_draft ? 'Save as Draft' : 'Move to Draft';
$wizard_context = [
    'title' => $values['title'],
    'job_code' => $values['job_code'],
    'status' => $current_status,
];
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php include __DIR__ . '/partials/job_wizard_form.php'; ?>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
