<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings_helpers.php';

$db_file = __DIR__ . '/admin/cpvia_database.sqlite';
$job = null;

$job_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    if ($job_id > 0 && file_exists($db_file)) {
        $pdo = cpvia_db($db_file);
        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $job = null;
}

include 'header.php';
?>
<link rel="stylesheet" href="assets/CSS/careers.css">

<?php if (!$job): ?>
    <div class="job-detail-not-found">
        <div class="job-detail-not-found-inner">
            <div class="job-detail-not-found-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <h2>Job Not Found</h2>
            <p>The job posting you are looking for does not exist or has been removed.</p>
            <a href="careers" class="btn-back-careers">Back to Careers</a>
        </div>
    </div>
<?php else: ?>
    <div class="job-detail-wrap">
        <a href="careers" class="job-detail-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Careers
        </a>

        <?php if ($job['status'] === 'Closed'): ?>
            <div class="job-detail-closed-notice">
                This job posting has been closed by CPVIA.
            </div>
        <?php endif; ?>

        <div class="job-detail-header">
            <h1><?php echo htmlspecialchars($job['title']); ?></h1>
            <div class="job-detail-meta">
                <?php if (!empty($job['department'])): ?>
                    <span class="job-dept-badge"><?php echo htmlspecialchars($job['department']); ?></span>
                <?php endif; ?>
                <?php if (!empty($job['location'])): ?>
                    <span class="job-meta-pill">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?php echo htmlspecialchars($job['location']); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($job['employment_type'])): ?>
                    <span class="job-meta-pill">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                        <?php echo htmlspecialchars($job['employment_type']); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($job['work_mode'])): ?>
                    <span class="job-meta-pill">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <?php echo htmlspecialchars($job['work_mode']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($job['description'])): ?>
            <div class="job-detail-section">
                <h3>Description</h3>
                <div class="job-detail-content"><?php echo nl2br(htmlspecialchars($job['description'])); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($job['requirements'])): ?>
            <div class="job-detail-section">
                <h3>Requirements</h3>
                <div class="job-detail-content"><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($job['responsibilities'])): ?>
            <div class="job-detail-section">
                <h3>Responsibilities</h3>
                <div class="job-detail-content"><?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($job['benefits'])): ?>
            <div class="job-detail-section">
                <h3>Benefits</h3>
                <div class="job-detail-content"><?php echo nl2br(htmlspecialchars($job['benefits'])); ?></div>
            </div>
        <?php endif; ?>

        <?php
        // Build salary info string if data is available
        $salary_info = '';
        if (!empty($job['min_salary']) || !empty($job['max_salary'])) {
            $currency = !empty($job['currency']) ? htmlspecialchars($job['currency']) : '';
            $salary_type = !empty($job['salary_type']) ? htmlspecialchars($job['salary_type']) : '';
            if (!empty($job['min_salary']) && !empty($job['max_salary'])) {
                $salary_info = $currency . ' ' . number_format((float)$job['min_salary']) . ' – ' . number_format((float)$job['max_salary']);
            } elseif (!empty($job['min_salary'])) {
                $salary_info = $currency . ' ' . number_format((float)$job['min_salary']) . '+';
            } else {
                $salary_info = 'Up to ' . $currency . ' ' . number_format((float)$job['max_salary']);
            }
            if ($salary_type) {
                $salary_info .= ' (' . $salary_type . ')';
            }
        }
        ?>
        <?php if ($salary_info): ?>
            <div class="job-detail-section">
                <h3>Salary</h3>
                <div class="job-detail-content"><p><?php echo $salary_info; ?></p></div>
            </div>
        <?php endif; ?>

        <?php if ($job['status'] === 'Active'): ?>
            <div class="job-detail-apply">
                <a href="apply.php?job_id=<?php echo (int) $job['id']; ?>" class="btn-apply-large">Apply Now</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
