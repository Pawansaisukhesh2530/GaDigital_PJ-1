<?php
/**
 * Admin Dashboard - main landing page for the admin panel.
 * Shows quick-access feature cards and summary counts pulled from SQLite via PDO.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$total_applications = 0;
$total_jobs = 0;
$total_active_jobs = 0;
$total_new_applications = 0;
$recent_applications = [];
$recent_jobs = [];
$error = '';

function status_class(string $status): string
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}

function job_status_class(string $status): string
{
    return 'job-status-' . strtolower(str_replace(' ', '-', $status));
}

try {
    if (file_exists($db_file)) {
        $pdo = cpvia_db($db_file);

        $total_applications = (int) $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        $total_jobs = (int) $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();

        $active_stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE status = ?");
        $active_stmt->execute(['Active']);
        $total_active_jobs = (int) $active_stmt->fetchColumn();

        $new_stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE status = ?");
        $new_stmt->execute(['New']);
        $total_new_applications = (int) $new_stmt->fetchColumn();

        $recent_applications = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $recent_jobs = $pdo->query("SELECT * FROM jobs ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Database file not found. Please initialize the database first.";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$page_title = 'Dashboard';
$active_nav = 'dashboard';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="welcome-banner">
    <div class="welcome-banner-text">
        <h2>Welcome to CPVIA Admin Dashboard</h2>
        <p>Manage candidate applications and career opportunities for CPVIA from one central place. Use the quick actions below to get started.</p>
    </div>
    <div class="welcome-banner-actions">
        <a href="applications.php" class="btn-primary-pill">View Applications</a>
        <a href="add_job.php" class="btn-outline-pill" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.5);">+ Add Job</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
        </div>
        <div>
            <div class="stat-number"><?php echo htmlspecialchars((string) $total_applications); ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
    </div>

    <div class="stat-card stat-orange">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
        </div>
        <div>
            <div class="stat-number"><?php echo htmlspecialchars((string) $total_new_applications); ?></div>
            <div class="stat-label">New Applications</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
        <div>
            <div class="stat-number"><?php echo htmlspecialchars((string) $total_jobs); ?></div>
            <div class="stat-label">Total Jobs</div>
        </div>
    </div>

    <div class="stat-card stat-orange">
        <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <div>
            <div class="stat-number"><?php echo htmlspecialchars((string) $total_active_jobs); ?></div>
            <div class="stat-label">Active Jobs</div>
        </div>
    </div>
</div>

<p class="section-heading">Quick Actions</p>
<div class="card-grid">
    <div class="feature-card">
        <div class="card-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        </div>
        <h3>Applications</h3>
        <p>Manage all candidate applications.</p>
        <a href="applications.php" class="btn-primary-pill">Open Applications</a>
    </div>

    <div class="feature-card">
        <div class="card-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
        <h3>Jobs</h3>
        <p>Manage all career opportunities.</p>
        <a href="jobs.php" class="btn-primary-pill">Open Jobs</a>
    </div>
</div>

<div class="dashboard-grid-2col">
    <div class="admin-panel">
        <p class="section-heading">Recent Applications <a href="applications.php">View all &rarr;</a></p>
        <?php if (count($recent_applications) > 0): ?>
            <div class="mini-list">
                <?php foreach ($recent_applications as $app): ?>
                <div class="mini-list-item">
                    <div>
                        <div class="mini-title"><?php echo htmlspecialchars($app['name']); ?></div>
                        <div class="mini-sub"><?php echo htmlspecialchars($app['job_title']); ?> &bull; <?php echo htmlspecialchars(date('M d, Y', strtotime($app['created_at']))); ?></div>
                    </div>
                    <span class="status-badge <?php echo htmlspecialchars(status_class($app['status'] ?? 'New')); ?>"><?php echo htmlspecialchars($app['status'] ?? 'New'); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">No applications received yet.</div>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <p class="section-heading">Recent Jobs <a href="jobs.php">View all &rarr;</a></p>
        <?php if (count($recent_jobs) > 0): ?>
            <div class="mini-list">
                <?php foreach ($recent_jobs as $job): ?>
                <div class="mini-list-item">
                    <div>
                        <div class="mini-title"><?php echo htmlspecialchars($job['title']); ?></div>
                        <div class="mini-sub"><?php echo htmlspecialchars($job['department']); ?> &bull; <?php echo htmlspecialchars($job['location']); ?></div>
                    </div>
                    <span class="status-badge <?php echo htmlspecialchars(job_status_class($job['status'])); ?>"><?php echo htmlspecialchars($job['status']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">No jobs available.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
