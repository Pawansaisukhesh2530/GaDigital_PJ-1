<?php
/**
 * Jobs Management Dashboard
 * Lists all jobs from the `jobs` table with search + status filter,
 * and provides Add / Edit / Delete actions.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$jobs = [];
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Success/error flash messages passed via query string after redirects
$flash_success = $_GET['success'] ?? '';

function job_status_class(string $status): string
{
    return 'job-status-' . strtolower(str_replace(' ', '-', $status));
}

try {
    if (file_exists($db_file)) {
        $pdo = cpvia_db($db_file);

        $sql = "SELECT * FROM jobs WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR department LIKE ? OR location LIKE ?)";
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
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Database file not found. Please initialize the database first.";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$page_title = 'Jobs Management';
$active_nav = 'jobs';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Jobs', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';
?>

<div class="admin-panel">
    <div class="admin-panel-header">
        <div class="admin-panel-header-text">
            <h2>Jobs Management</h2>
            <p>Manage all available career opportunities.</p>
        </div>
        <div class="admin-panel-actions">
            <a href="add_job.php" class="btn-primary-pill">+ Add Job</a>
        </div>
    </div>

    <?php if ($flash_success === 'added'): ?>
        <div class="alert alert-success">Job added successfully.</div>
    <?php elseif ($flash_success === 'updated'): ?>
        <div class="alert alert-success">Job updated successfully.</div>
    <?php elseif ($flash_success === 'deleted'): ?>
        <div class="alert alert-success">Job deleted successfully.</div>
    <?php elseif ($flash_success === 'draft'): ?>
        <div class="alert alert-success">Draft saved successfully.</div>
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
                placeholder="Search jobs by title, department, or location..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <div class="search-actions">
                <button type="submit" class="btn-search">Search</button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="jobs.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <form method="GET" class="filter-form">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <label for="status_filter">Filter by Status:</label>
            <select name="status" id="status_filter">
                <option value="" <?php echo empty($status_filter) ? 'selected' : ''; ?>>All</option>
                <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Draft" <?php echo $status_filter === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="Closed" <?php echo $status_filter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
            <button type="submit" class="btn-search">Filter</button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Job Title</th>
                    <th>Department</th>
                    <th>Location</th>
                    <th>Employment Type</th>
                    <th>Status</th>
                    <th>Posted Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($jobs) > 0): ?>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($job['id']); ?></td>
                        <td><span class="job-badge"><?php echo htmlspecialchars($job['title']); ?></span></td>
                        <td><?php echo htmlspecialchars($job['department']); ?></td>
                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                        <td><?php echo htmlspecialchars($job['employment_type']); ?></td>
                        <td>
                            <span class="status-badge <?php echo htmlspecialchars(job_status_class($job['status'])); ?>">
                                <?php echo htmlspecialchars($job['status']); ?>
                            </span>
                        </td>
                        <td class="date-text"><?php echo htmlspecialchars(date('M d, Y', strtotime($job['created_at']))); ?></td>
                        <td>
                            <div class="action-group">
                                <a href="edit_job.php?id=<?php echo htmlspecialchars($job['id']); ?>" class="icon-btn icon-btn-edit" title="Edit Job" aria-label="Edit Job">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </a>
                                <form action="delete_job.php" method="POST" class="icon-btn-delete-form" onsubmit="return confirm('Are you sure you want to delete this job?');">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($job['id']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">
                                    <button type="submit" class="icon-btn icon-btn-delete" title="Delete Job" aria-label="Delete Job">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="no-data">
                            <span class="no-data-icon">&#128203;</span>
                            No jobs available.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
