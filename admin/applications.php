<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$applications = [];
$status_options = ['New', 'In Review', 'Shortlisted', 'Rejected', 'Hired'];

// Flash message shown after redirect from delete_application.php
$deleted_flash = $_GET['deleted'] ?? '';

function status_class(string $status): string
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

try {
    if (file_exists($db_file)) {
        $pdo = cpvia_db($db_file);

        $search = $_GET['search'] ?? '';
        $status_filter = $_GET['status'] ?? '';

        $sql = "SELECT * FROM applications WHERE 1=1";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR job_title LIKE ?)";
            $keyword = "%$search%";

            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }
        if (!empty($status_filter)) {
            $sql .= " AND status = ?";
            $params[] = $status_filter;
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Database file not found. Please initialize the database first.";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$page_title = 'Applications Management';
$active_nav = 'applications';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Applications', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';
?>

<div class="admin-panel">
    <div class="admin-panel-header">
        <div class="admin-panel-header-text">
            <h2>Job Applications</h2>
            <p>Review and manage all candidate applications.</p>
        </div>
        <div class="admin-panel-actions">
            <a href="../careers.php" target="_blank" rel="noopener" class="btn-outline-pill">View Careers Page</a>
        </div>
    </div>

    <?php if ($deleted_flash === 'success'): ?>
        <div class="alert alert-success">Application deleted successfully.</div>
    <?php elseif ($deleted_flash === 'notfound'): ?>
        <div class="alert alert-warning">That application could not be found. It may have already been deleted.</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="toolbar-panel">
        <form method="GET" class="search-bar">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by name, email, or job title..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <div class="search-actions">
                <button type="submit" class="btn-search">Search</button>

                <?php if(!empty($search) || !empty($status_filter)): ?>
                    <a href="applications.php" class="btn-clear">Clear Search</a>
                <?php endif; ?>
            </div>
        </form>

        <form method="GET" class="filter-form">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <label for="status_filter">Filter by Status:</label>
            <select name="status" id="status_filter">
                <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All</option>
                <?php
                    $statuses = ["New", "In Review", "Shortlisted", "Rejected", "Hired"];
                    foreach ($statuses as $status){
                        $selected = ($status_filter === $status) ? 'selected' : '';
                        echo "<option value=\"$status\" $selected>$status</option>";
                    }
                ?>
            </select>
            <button type="submit" class="btn-search">Filter</button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Position</th>
                    <th>Applicant Details</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Resume</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($applications) > 0): ?>
                    <?php foreach ($applications as $app): ?>
                    <?php $current_status = $app['status'] ?? 'New'; ?>
                    <?php $detail_url = 'application_details.php?id=' . (int) $app['id']; ?>
                    <tr class="clickable-row" data-href="<?php echo htmlspecialchars($detail_url); ?>" tabindex="0" title="Open applicant profile">
                        <td>#<?php echo htmlspecialchars($app['id']); ?></td>
                        <td class="date-text"><?php echo htmlspecialchars(date('M d, Y', strtotime($app['created_at']))); ?></td>
                        <td><span class="job-badge"><?php echo htmlspecialchars($app['job_title']); ?></span></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($detail_url); ?>" class="applicant-name-link"><?php echo htmlspecialchars($app['name']); ?></a><br>
                        </td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" style="color: #3D1A8A; text-decoration: none;"><?php echo htmlspecialchars($app['email']); ?></a><br>
                            <span style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($app['phone']); ?></span>
                        </td>
                        <td>
                            <div class="status-cell">
                                <span class="status-badge <?php echo htmlspecialchars(status_class($current_status)); ?>">
                                    <?php echo htmlspecialchars($current_status); ?>
                                </span>

                                <form action="update_status.php" method="POST" class="status-form">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($app['id']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">
                                    <select name="status">
                                        <?php foreach ($status_options as $status): ?>
                                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $current_status === $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Save</button>
                                </form>
                            </div>
                        </td>

                        <td>
                            <a href="../uploads/resumes/<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn-download">
                                View Resume
                            </a>
                        </td>

                        <td>
                            <div class="action-group">
                                <form action="delete_application.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this application?\nThis action cannot be undone.');">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($app['id']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">
                                    <button type="submit" class="btn-delete" title="Delete Application" aria-label="Delete Application">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="no-data"><span class="no-data-icon">&#128203;</span>No applications received yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var INTERACTIVE = 'a, button, select, input, textarea, label, form';
    document.querySelectorAll('tr.clickable-row').forEach(function (row) {
        var url = row.getAttribute('data-href');
        if (!url) { return; }
        row.addEventListener('click', function (e) {
            if (e.target.closest(INTERACTIVE)) { return; }
            window.location.href = url;
        });
        row.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter') && !e.target.closest(INTERACTIVE)) { window.location.href = url; }
        });
    });
});
</script>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
