<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings_helpers.php';

$db_file = __DIR__ . '/admin/cpvia_database.sqlite';
$jobs = [];
$careers_summary = '';
$filter_options = ['location' => [], 'employment_type' => [], 'skills' => []];
$job_skills_map = [];

try {
    if (file_exists($db_file)) {
        $pdo = cpvia_db($db_file);

        $careers_summary = cpvia_get_setting($pdo, 'careers_intro_summary');

        // Fetch both Active and Closed jobs, with Active listed first
        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE status IN ('Active', 'Closed') ORDER BY CASE status WHEN 'Active' THEN 0 ELSE 1 END, created_at DESC");
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build filter options from active jobs ONLY (not closed)
        $active_jobs = array_filter($jobs, fn($j) => $j['status'] === 'Active');
        $filter_options = [
            'location' => array_values(array_unique(array_filter(array_column($active_jobs, 'location')))),
            'employment_type' => array_values(array_unique(array_filter(array_column($active_jobs, 'employment_type')))),
            'skills' => cpvia_active_job_skills($pdo),
        ];

        // Fetch skills per job for data-skills attribute
        $job_skills_map = [];
        if (!empty($jobs)) {
            try {
                $stmt = $pdo->prepare("
                    SELECT js.job_id, s.name
                    FROM job_skills js
                    INNER JOIN skills s ON s.id = js.skill_id
                    WHERE js.job_id IN (" . implode(',', array_map('intval', array_column($jobs, 'id'))) . ")
                ");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $job_skills_map[(int)$row['job_id']][] = $row['name'];
                }
            } catch (Throwable $e) {
                // Graceful fallback — skills just won't be filterable
            }
        }
    }
} catch (Exception $e) {
    $jobs = [];
}

/**
 * Build a short preview from a longer text block (description/requirements).
 */
function careers_preview(string $text, int $limit = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(substr($text, 0, $limit)) . '…';
}

include 'header.php';
?>
<link rel="stylesheet" href="assets/CSS/careers.css">

<div class="careers-hero">
    <span class="careers-subtitle">JOIN OUR TEAM</span>
    <h1>Build Your Career With CPVIA</h1>
    <p>Explore opportunities to work with industry-leading experts in clinical research, biostatistics, and biometrics.</p>
</div>

<section class="careers-intro">
    <p><?php echo htmlspecialchars($careers_summary); ?></p>
</section>

<?php include __DIR__ . '/partials/filter_bar.php'; ?>

<?php
$active_count = count(array_filter($jobs, fn($j) => $j['status'] === 'Active'));
?>
<?php if (count($jobs) > 0): ?>
    <div class="careers-count-bar">
        <h2>Open Positions</h2>
        <span><?php echo $active_count; ?> <?php echo $active_count === 1 ? 'opportunity' : 'opportunities'; ?> available</span>
    </div>

    <div class="job-list">
        <?php foreach ($jobs as $job): ?>
        <?php $is_closed = ($job['status'] === 'Closed'); ?>
        <div class="job-card<?php echo $is_closed ? ' job-card--closed' : ''; ?>" data-location="<?php echo htmlspecialchars($job['location']); ?>" data-type="<?php echo htmlspecialchars($job['employment_type']); ?>" data-skills="<?php echo htmlspecialchars(implode(',', $job_skills_map[(int)$job['id']] ?? [])); ?>">
            <div class="job-card-top">
                <div class="job-card-title-group">
                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                    <div class="job-meta-row">
                        <span class="job-dept-badge"><?php echo htmlspecialchars($job['department']); ?></span>
                        <?php if ($is_closed): ?>
                        <span class="badge-closed">Closed</span>
                        <?php endif; ?>
                        <span class="job-meta-pill">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <?php echo htmlspecialchars($job['location']); ?>
                        </span>
                        <span class="job-meta-pill">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                            <?php echo htmlspecialchars($job['employment_type']); ?>
                        </span>
                    </div>
                </div>
                <span class="job-posted-date">Posted <?php echo htmlspecialchars(date('M d, Y', strtotime($job['created_at']))); ?></span>
            </div>

            <div class="job-card-body">
                <div>
                    <h4>Description</h4>
                    <p><?php echo htmlspecialchars(careers_preview($job['description'])); ?></p>
                </div>
                <?php if (!empty($job['requirements'])): ?>
                <div>
                    <h4>Requirements</h4>
                    <p><?php echo htmlspecialchars(careers_preview($job['requirements'], 130)); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="job-card-footer">
                <span class="job-id-tag">Job ID #<?php echo htmlspecialchars($job['id']); ?></span>
                <?php if ($is_closed): ?>
                <a href="job_detail.php?id=<?php echo htmlspecialchars($job['id']); ?>" class="btn-view">View Details</a>
                <?php else: ?>
                <a href="apply.php?job_id=<?php echo htmlspecialchars($job['id']); ?>" class="btn-apply">Apply Now</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="no-jobs">
        <div class="no-jobs-inner">
            <div class="no-jobs-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
            </div>
            <h3>No job openings are currently available.</h3>
            <p>Please check back later &mdash; new opportunities are posted regularly.</p>
        </div>
    </div>
<?php endif; ?>

<script src="assets/js/careers_filter.js"></script>
<?php include 'footer.php'; ?>
