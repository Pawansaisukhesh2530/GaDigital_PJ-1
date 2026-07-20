<?php
/**
 * Edit Job - loads an existing job and updates it in the `jobs` table.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$error = '';
$job_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);

$values = [
    'title' => '',
    'department' => '',
    'location' => '',
    'employment_type' => 'Full-Time',
    'description' => '',
    'requirements' => '',
    'status' => 'Active',
];

if ($job_id <= 0) {
    header('Location: jobs.php');
    exit;
}

try {
    $pdo = cpvia_db($db_file);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $values['title'] = trim($_POST['title'] ?? '');
        $values['department'] = trim($_POST['department'] ?? '');
        $values['location'] = trim($_POST['location'] ?? '');
        $values['employment_type'] = trim($_POST['employment_type'] ?? '');
        $values['description'] = trim($_POST['description'] ?? '');
        $values['requirements'] = trim($_POST['requirements'] ?? '');
        $values['status'] = trim($_POST['status'] ?? 'Active');

        $allowed_statuses = ['Active', 'Closed'];

        if (!cpvia_csrf_check($_POST['csrf_token'] ?? null)) {
            $error = 'Your session has expired. Please try again.';
        } elseif (
            $values['title'] === '' ||
            $values['department'] === '' ||
            $values['location'] === '' ||
            $values['employment_type'] === '' ||
            $values['description'] === '' ||
            $values['requirements'] === ''
        ) {
            $error = 'Please fill in all required fields.';
        } elseif (!in_array($values['status'], $allowed_statuses, true)) {
            $error = 'Invalid status selected.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE jobs
                SET title = ?, department = ?, location = ?, employment_type = ?, description = ?, requirements = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $values['title'],
                $values['department'],
                $values['location'],
                $values['employment_type'],
                $values['description'],
                $values['requirements'],
                $values['status'],
                $job_id,
            ]);

            header('Location: jobs.php?success=updated');
            exit;
        }
    } else {
        // Load existing job for pre-fill
        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            header('Location: jobs.php');
            exit;
        }

        $values['title'] = $job['title'];
        $values['department'] = $job['department'];
        $values['location'] = $job['location'];
        $values['employment_type'] = $job['employment_type'];
        $values['description'] = $job['description'];
        $values['requirements'] = $job['requirements'];
        $values['status'] = $job['status'];
    }
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

$page_title = 'Edit Job';
$active_nav = 'jobs';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Jobs', 'url' => 'jobs.php'],
    ['label' => 'Edit Job', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" action="edit_job.php?id=<?php echo htmlspecialchars($job_id); ?>">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($job_id); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">

    <div class="form-grid-2col">
        <div class="form-section-card">
            <h3>Job Details</h3>
            <p class="form-section-sub">Core information candidates will see on the careers page.</p>

            <div class="form-group">
                <label for="title">Job Title *</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($values['title']); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="department">Department *</label>
                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($values['department']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($values['location']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" rows="6" required><?php echo htmlspecialchars($values['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="requirements">Requirements *</label>
                <textarea id="requirements" name="requirements" rows="6" required><?php echo htmlspecialchars($values['requirements']); ?></textarea>
            </div>
        </div>

        <div class="form-section-card">
            <h3>Job Settings</h3>
            <p class="form-section-sub">Employment type and visibility status.</p>

            <div class="form-group">
                <label for="employment_type">Employment Type *</label>
                <select id="employment_type" name="employment_type" required>
                    <?php foreach (['Full-Time', 'Part-Time', 'Contract', 'Internship', 'Remote'] as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $values['employment_type'] === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status *</label>
                <select id="status" name="status" required>
                    <option value="Active" <?php echo $values['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Closed" <?php echo $values['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>

            <div class="form-actions-bar">
                <a href="jobs.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Update Job</button>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
