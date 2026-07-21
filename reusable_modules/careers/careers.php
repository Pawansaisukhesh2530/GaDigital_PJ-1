<?php
require_once __DIR__ . '/db.php';

$db_file = __DIR__ . '/admin/cpvia_database.sqlite';
$jobs = [];

try {
    if (file_exists($db_file)) {
        $pdo = cpvia_db($db_file);

        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute(['Active']);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<style>
.careers-hero {
    padding: 8rem 5% 3.5rem;
    background: radial-gradient(circle at 15% 20%, #F4F2FF 0%, #FAFAFF 55%, #FFF6F0 100%);
    text-align: center;
}
.careers-hero .careers-subtitle {
    background: rgba(61, 26, 138, 0.06);
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--primary-blue);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    letter-spacing: 1px;
}
.careers-hero h1 {
    font-size: 3.2rem;
    color: #1a0f3d;
    margin-bottom: 1rem;
    font-weight: 900;
}
.careers-hero p {
    color: var(--text-light);
    font-size: 1.1rem;
    max-width: 620px;
    margin: 0 auto;
}

.careers-count-bar {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2.2rem 5% 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.careers-count-bar h2 {
    font-size: 1.4rem;
    color: var(--primary-blue);
    font-weight: 800;
    margin: 0;
}
.careers-count-bar span {
    color: var(--text-light);
    font-size: 0.9rem;
}

.job-list {
    max-width: 1400px;
    margin: 2rem auto 4rem;
    padding: 0 5%;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.75rem;
}

.job-card {
    background: #fff;
    border: 1px solid #eaeaea;
    border-radius: 18px;
    padding: 2rem;
    box-shadow: 0 4px 18px rgba(61, 26, 138, 0.05);
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}

.job-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 45px rgba(61, 26, 138, 0.12);
    border-color: rgba(255, 85, 0, 0.25);
}

.job-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.job-card-title-group h3 {
    font-size: 1.4rem;
    color: var(--primary-blue);
    margin-bottom: 0.6rem;
    font-weight: 800;
    line-height: 1.3;
}

.job-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}

.job-dept-badge {
    background: #F0EBF7;
    color: var(--primary-blue);
    padding: 0.35rem 0.9rem;
    border-radius: 30px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

.job-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #fafafa;
    border: 1px solid #eee;
    color: var(--text-light);
    padding: 0.35rem 0.85rem;
    border-radius: 30px;
    font-size: 0.78rem;
    font-weight: 600;
}

.job-posted-date {
    color: #aaa;
    font-size: 0.78rem;
    white-space: nowrap;
    flex-shrink: 0;
    padding-top: 0.2rem;
}

.job-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.job-card-body h4 {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--primary-orange);
    font-weight: 800;
    margin: 0;
}

.job-card-body p {
    color: var(--text-light);
    font-size: 0.92rem;
    line-height: 1.6;
    margin: 0;
}

.job-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-top: 0.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f2f2f2;
}

.job-card-footer .job-id-tag {
    color: #bbb;
    font-size: 0.78rem;
    font-weight: 600;
}

.job-card .btn-apply {
    background: #FF5500;
    color: #fff;
    padding: 0.75rem 1.9rem;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 700;
    transition: background 0.3s, transform 0.2s;
    text-transform: uppercase;
    font-size: 0.8rem;
    white-space: nowrap;
}
.job-card .btn-apply:hover {
    background: #e04a00;
    color: #fff;
    transform: translateY(-2px);
}

@media (max-width: 1200px) {
    .job-list { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .careers-hero { padding: 7rem 6% 2.5rem; }
    .careers-hero h1 { font-size: 2.3rem; }
    .careers-count-bar { padding: 1.75rem 6% 0; }
    .job-list { padding: 0 6%; gap: 1.25rem; margin: 1.5rem auto 3rem; }
    .job-card { padding: 1.5rem; }
    .job-card-top { flex-direction: column; }
    .job-card-footer { flex-direction: column; align-items: stretch; gap: 0.75rem; }
    .job-card .btn-apply { text-align: center; }
}

.no-jobs {
    max-width: 1400px;
    margin: 2rem auto 5rem;
    padding: 0 5%;
}
.no-jobs .no-jobs-inner {
    background: #fff;
    border: 1px solid #eaeaea;
    border-radius: 18px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 4px 18px rgba(61, 26, 138, 0.05);
}
.no-jobs .no-jobs-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: #F0EBF7;
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}
.no-jobs h3 {
    font-size: 1.5rem;
    color: var(--primary-blue);
    margin-bottom: 0.6rem;
    font-weight: 800;
}
.no-jobs p {
    color: var(--text-light);
    margin-bottom: 0;
}
</style>

<div class="careers-hero">
    <span class="careers-subtitle">JOIN OUR TEAM</span>
    <h1>Build Your Career With CPVIA</h1>
    <p>Explore opportunities to work with industry-leading experts in clinical research, biostatistics, and biometrics.</p>
</div>

<?php if (count($jobs) > 0): ?>
    <div class="careers-count-bar">
        <h2>Open Positions</h2>
        <span><?php echo count($jobs); ?> <?php echo count($jobs) === 1 ? 'opportunity' : 'opportunities'; ?> available</span>
    </div>

    <div class="job-list">
        <?php foreach ($jobs as $job): ?>
        <div class="job-card">
            <div class="job-card-top">
                <div class="job-card-title-group">
                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                    <div class="job-meta-row">
                        <span class="job-dept-badge"><?php echo htmlspecialchars($job['department']); ?></span>
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
                <a href="apply.php?job_id=<?php echo htmlspecialchars($job['id']); ?>" class="btn-apply">Apply Now</a>
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

<?php include 'footer.php'; ?>
