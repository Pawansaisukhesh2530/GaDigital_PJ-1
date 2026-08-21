<?php
/**
 * Two-Step Delete Job Flow
 *
 * Step 1: POST with id + csrf_token → shows confirmation page
 * Step 2: POST with id + csrf_token + action=confirm_delete → cascading delete
 *
 * Requirements covered: 6.4
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: jobs.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$csrf_token = $_POST['csrf_token'] ?? '';
$action = $_POST['action'] ?? '';

// Validate CSRF token
if (!cpvia_csrf_check($csrf_token)) {
    die('Invalid or expired session token. Please go back and try again.');
}

// Validate job ID
if ($id <= 0) {
    header('Location: jobs.php');
    exit;
}

try {
    $pdo = cpvia_db($db_file);
} catch (Exception $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 2: Confirmed delete — execute cascading delete in a transaction
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'confirm_delete') {
    try {
        $pdo->beginTransaction();

        // 1. Delete application_skills for all applications of this job
        $stmt = $pdo->prepare(
            "DELETE FROM application_skills WHERE application_id IN (SELECT id FROM applications WHERE job_id = ?)"
        );
        $stmt->execute([$id]);

        // 2. Delete application_professional_details for all applications of this job
        $stmt = $pdo->prepare(
            "DELETE FROM application_professional_details WHERE application_id IN (SELECT id FROM applications WHERE job_id = ?)"
        );
        $stmt->execute([$id]);

        // 3. Delete application_education for all applications of this job
        $stmt = $pdo->prepare(
            "DELETE FROM application_education WHERE application_id IN (SELECT id FROM applications WHERE job_id = ?)"
        );
        $stmt->execute([$id]);

        // 4. Delete application_documents for all applications of this job
        $stmt = $pdo->prepare(
            "DELETE FROM application_documents WHERE application_id IN (SELECT id FROM applications WHERE job_id = ?)"
        );
        $stmt->execute([$id]);

        // 5. Delete applications for this job
        $stmt = $pdo->prepare("DELETE FROM applications WHERE job_id = ?");
        $stmt->execute([$id]);

        // 6. Delete job_skills for this job
        $stmt = $pdo->prepare("DELETE FROM job_skills WHERE job_id = ?");
        $stmt->execute([$id]);

        // 7. Delete the job itself
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        header('Location: jobs.php?success=deleted');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die('Database error during deletion: ' . htmlspecialchars($e->getMessage()));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 1: Initial delete request — show confirmation page
// ─────────────────────────────────────────────────────────────────────────────

// Fetch job details to display in the confirmation page
$stmt = $pdo->prepare("SELECT id, title FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: jobs.php');
    exit;
}

// Generate a fresh CSRF token for the confirmation form
$fresh_csrf = cpvia_csrf_token();

// Render confirmation page using admin layout
$page_title = 'Confirm Delete';
$active_nav = 'jobs';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Jobs', 'url' => 'jobs.php'],
    ['label' => 'Confirm Delete', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';
?>

<div class="admin-panel">
    <div class="admin-panel-header">
        <div class="admin-panel-header-text">
            <h2>Confirm Deletion</h2>
        </div>
    </div>

    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
        <strong>⚠️ Warning:</strong> Permanently delete this job posting AND all associated application records?
    </div>

    <div style="background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem;">
        <p style="margin: 0; font-size: 1rem; color: #333;">
            <strong>Job Title:</strong> <?php echo htmlspecialchars($job['title']); ?>
        </p>
        <p style="margin: 0.5rem 0 0; font-size: 0.875rem; color: #666;">
            This action cannot be undone. All applications, skills, documents, and education records associated with this job will be permanently removed.
        </p>
    </div>

    <div style="display: flex; gap: 1rem; align-items: center;">
        <form action="delete_job.php" method="POST" style="display: inline;">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($job['id']); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($fresh_csrf); ?>">
            <input type="hidden" name="action" value="confirm_delete">
            <button type="submit" class="btn-primary-pill" style="background: #dc3545; border-color: #dc3545;">
                Confirm Delete
            </button>
        </form>
        <a href="jobs.php" class="btn-primary-pill" style="background: #6c757d; border-color: #6c757d; text-decoration: none;">
            Cancel
        </a>
    </div>
</div>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
