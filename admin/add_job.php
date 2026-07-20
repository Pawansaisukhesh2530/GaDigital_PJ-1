<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$error = '';

$values = [
    'title' => '',
    'department' => '',
    'location' => '',
    'employment_type' => 'Full-Time',
    'description' => '',
    'requirements' => '',
    'status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title'] = trim($_POST['title'] ?? '');
    $values['department'] = trim($_POST['department'] ?? '');
    $values['location'] = trim($_POST['location'] ?? '');
    $values['employment_type'] = trim($_POST['employment_type'] ?? '');
    $values['description'] = trim($_POST['description'] ?? '');
    $values['requirements'] = trim($_POST['requirements'] ?? '');
    $values['status'] = trim($_POST['status'] ?? 'Active');

    $allowed_statuses = ['Active', 'Closed'];

    // Validate required fields
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
        try {
            $pdo = cpvia_db($db_file);

            $stmt = $pdo->prepare("
                INSERT INTO jobs (title, department, location, employment_type, description, requirements, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $values['title'],
                $values['department'],
                $values['location'],
                $values['employment_type'],
                $values['description'],
                $values['requirements'],
                $values['status'],
            ]);

            header('Location: jobs.php?success=added');
            exit;
        } catch (Exception $e) {
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
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" action="">
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
                <button type="submit" class="btn-submit">Save Job</button>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
