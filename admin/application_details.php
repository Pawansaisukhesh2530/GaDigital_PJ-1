<?php
/**
 * application_details.php — Applicant Profile (Admin)
 * -----------------------------------------------------------------------------
 * Full read-only profile of a single application assembled from the recruitment
 * schema, plus inline status update / delete / resume actions. Reuses the shared
 * admin layout. Read via prepared statements; all output escaped.
 * -----------------------------------------------------------------------------
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$db_file = __DIR__ . '/cpvia_database.sqlite';
$status_options = ['New', 'In Review', 'Shortlisted', 'Rejected', 'Hired'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: applications.php?deleted=notfound');
    exit;
}

$updated_flash = isset($_GET['updated']);

function status_class(string $status): string
{
    return 'status-' . strtolower(str_replace(' ', '-', $status));
}
/** Escape or dash. */
function dv($v): string
{
    $v = is_string($v) ? trim($v) : $v;
    return ($v === null || $v === '' ) ? '—' : htmlspecialchars((string) $v);
}
function money($v, string $cur): string
{
    if ($v === null || $v === '') { return '—'; }
    return htmlspecialchars($cur . ' ' . number_format((float) $v));
}

try {
    $pdo = cpvia_db($db_file);

    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        header('Location: applications.php?deleted=notfound');
        exit;
    }

    // Job context (optional — legacy applications may have no job_id).
    $job = null;
    if (!empty($app['job_id'])) {
        $js = $pdo->prepare("SELECT title, department, job_code, employment_type, work_mode, location FROM jobs WHERE id = ?");
        $js->execute([(int) $app['job_id']]);
        $job = $js->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $pf = $pdo->prepare("SELECT * FROM application_professional_details WHERE application_id = ?");
    $pf->execute([$id]);
    $prof = $pf->fetch(PDO::FETCH_ASSOC) ?: [];

    $ed = $pdo->prepare("SELECT * FROM application_education WHERE application_id = ? ORDER BY id ASC");
    $ed->execute([$id]);
    $education = $ed->fetchAll(PDO::FETCH_ASSOC);

    $dc = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ?");
    $dc->execute([$id]);
    $docs = [];
    foreach ($dc->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $docs[$d['document_type']] = $d;
    }

    $sk = $pdo->prepare("SELECT s.id, s.name FROM application_skills a JOIN skills s ON s.id = a.skill_id WHERE a.application_id = ? ORDER BY s.name COLLATE NOCASE");
    $sk->execute([$id]);
    $cand_skills = $sk->fetchAll(PDO::FETCH_ASSOC);
    $cand_skill_ids = array_map(static fn($r) => (int) $r['id'], $cand_skills);

    // Job skill requirements (to compute matched / additional).
    $required_skills = [];
    $preferred_skill_ids = [];
    if (!empty($app['job_id'])) {
        $jsk = $pdo->prepare("SELECT s.id, s.name, js.skill_type FROM job_skills js JOIN skills s ON s.id = js.skill_id WHERE js.job_id = ?");
        $jsk->execute([(int) $app['job_id']]);
        foreach ($jsk->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['skill_type'] === 'required') {
                $required_skills[(int) $r['id']] = $r['name'];
            } else {
                $preferred_skill_ids[] = (int) $r['id'];
            }
        }
    }
    $required_ids = array_keys($required_skills);
    $matched_ids = array_values(array_intersect($cand_skill_ids, $required_ids));
    $known_ids = array_merge($required_ids, $preferred_skill_ids);
    $additional_skills = array_values(array_filter($cand_skills, static fn($r) => !in_array((int) $r['id'], $known_ids, true)));
} catch (Throwable $e) {
    header('Location: applications.php?deleted=notfound');
    exit;
}

$current_status = $app['status'] ?? 'New';
$resume_file = $app['resume_path'] ?? '';
$resume_url = $resume_file !== '' ? '../uploads/resumes/' . rawurlencode($resume_file) : '';
$resume_original = $docs['resume']['original_filename'] ?? $resume_file;
$cover = $docs['cover_letter'] ?? null;
$cover_url = $cover ? '../uploads/resumes/' . rawurlencode($cover['stored_filename']) : '';

$page_title = 'Applicant Profile';
$active_nav = 'applications';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Applications', 'url' => 'applications.php'],
    ['label' => $app['name'] ?? 'Applicant', 'url' => null],
];
include __DIR__ . '/partials/layout_top.php';
?>

<?php if ($updated_flash): ?>
    <div class="alert alert-success">Application status updated.</div>
<?php endif; ?>

<div class="profile-layout">
    <div class="profile-main">
        <!-- Section 1: Application Summary -->
        <div class="profile-card profile-hero">
            <div class="profile-hero-id">
                <div class="profile-avatar"><?php echo htmlspecialchars(strtoupper(substr($app['name'] ?? 'A', 0, 1))); ?></div>
                <div>
                    <h2><?php echo dv($app['name']); ?></h2>
                    <p class="profile-hero-sub"><?php echo dv($app['job_title']); ?> &bull; Application #<?php echo (int) $app['id']; ?></p>
                </div>
            </div>
            <span class="status-badge <?php echo htmlspecialchars(status_class($current_status)); ?>"><?php echo htmlspecialchars($current_status); ?></span>
        </div>

        <div class="profile-card">
            <h3 class="profile-section-title">Application Summary</h3>
            <div class="profile-grid">
                <div class="profile-item"><span>Application ID</span><strong>#<?php echo (int) $app['id']; ?></strong></div>
                <div class="profile-item"><span>Status</span><strong><?php echo htmlspecialchars($current_status); ?></strong></div>
                <div class="profile-item"><span>Applied Date</span><strong><?php echo htmlspecialchars(date('M d, Y', strtotime($app['created_at']))); ?></strong></div>
                <div class="profile-item"><span>Job Title</span><strong><?php echo dv($app['job_title']); ?></strong></div>
                <div class="profile-item"><span>Department</span><strong><?php echo dv($job['department'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Job Code</span><strong><?php echo dv($job['job_code'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Employment Type</span><strong><?php echo dv($job['employment_type'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Work Mode</span><strong><?php echo dv($job['work_mode'] ?? ''); ?></strong></div>
            </div>
        </div>

        <!-- Section 2: Personal Information -->
        <div class="profile-card">
            <h3 class="profile-section-title">Personal Information</h3>
            <div class="profile-grid">
                <div class="profile-item"><span>Full Name</span><strong><?php echo dv($app['name']); ?></strong></div>
                <div class="profile-item"><span>Email</span><strong><?php if (!empty($app['email'])): ?><a href="mailto:<?php echo htmlspecialchars($app['email']); ?>"><?php echo htmlspecialchars($app['email']); ?></a><?php else: ?>—<?php endif; ?></strong></div>
                <div class="profile-item"><span>Phone</span><strong><?php echo dv($app['phone']); ?></strong></div>
                <div class="profile-item"><span>Current Location</span><strong><?php echo dv($app['current_location']); ?></strong></div>
                <div class="profile-item"><span>LinkedIn</span><strong><?php if (!empty($app['linkedin_profile'])): ?><a href="<?php echo htmlspecialchars($app['linkedin_profile']); ?>" target="_blank" rel="noopener">View Profile</a><?php else: ?>—<?php endif; ?></strong></div>
            </div>
        </div>

        <!-- Section 3: Professional Information -->
        <div class="profile-card">
            <h3 class="profile-section-title">Professional Information</h3>
            <div class="profile-grid">
                <div class="profile-item"><span>Current Company</span><strong><?php echo dv($prof['current_company'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Current Designation</span><strong><?php echo dv($prof['current_designation'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Total Experience</span><strong><?php echo isset($prof['total_experience']) && $prof['total_experience'] !== null ? htmlspecialchars($prof['total_experience']) . ' yrs' : '—'; ?></strong></div>
                <div class="profile-item"><span>Relevant Experience</span><strong><?php echo isset($prof['relevant_experience']) && $prof['relevant_experience'] !== null ? htmlspecialchars($prof['relevant_experience']) . ' yrs' : '—'; ?></strong></div>
                <div class="profile-item"><span>Employment Status</span><strong><?php echo dv($prof['employment_status'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Notice Period</span><strong><?php echo dv($prof['notice_period'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Current CTC</span><strong><?php echo money($prof['current_ctc'] ?? null, $prof['ctc_currency'] ?? ''); ?></strong></div>
                <div class="profile-item"><span>Expected CTC</span><strong><?php echo money($prof['expected_ctc'] ?? null, $prof['ctc_currency'] ?? ''); ?></strong></div>
            </div>
        </div>

        <!-- Section 4: Education -->
        <div class="profile-card">
            <h3 class="profile-section-title">Education</h3>
            <?php if (empty($education)): ?>
                <p class="profile-empty">No education details provided.</p>
            <?php else: foreach ($education as $e): ?>
                <div class="profile-grid profile-grid-edu">
                    <div class="profile-item"><span>Qualification</span><strong><?php echo dv($e['qualification']); ?></strong></div>
                    <div class="profile-item"><span>Specialization</span><strong><?php echo dv($e['specialization']); ?></strong></div>
                    <div class="profile-item"><span>University / College</span><strong><?php echo dv($e['university_college']); ?></strong></div>
                    <div class="profile-item"><span>Graduation Year</span><strong><?php echo dv($e['graduation_year']); ?></strong></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Section 5: Skills -->
        <div class="profile-card">
            <h3 class="profile-section-title">Skills</h3>
            <?php if (!empty($required_skills)): ?>
                <div class="profile-skillblock">
                    <h4>Required by Job</h4>
                    <div class="profile-chips">
                        <?php foreach ($required_skills as $rid => $rname): $isMatch = in_array((int) $rid, $matched_ids, true); ?>
                            <span class="profile-chip <?php echo $isMatch ? 'chip-match' : 'chip-missing'; ?>">
                                <?php echo htmlspecialchars($rname); ?><?php echo $isMatch ? ' &#10003;' : ''; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="profile-skillblock">
                <h4><?php echo !empty($required_skills) ? 'Additional Candidate Skills' : 'Candidate Skills'; ?></h4>
                <?php $show = !empty($required_skills) ? $additional_skills : $cand_skills; ?>
                <?php if (empty($show)): ?>
                    <p class="profile-empty">No <?php echo !empty($required_skills) ? 'additional ' : ''; ?>skills listed.</p>
                <?php else: ?>
                    <div class="profile-chips">
                        <?php foreach ($show as $s): ?>
                            <span class="profile-chip"><?php echo htmlspecialchars($s['name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section 7: Additional Questions -->
        <div class="profile-card">
            <h3 class="profile-section-title">Additional Questions</h3>
            <div class="profile-qa">
                <div class="profile-q">
                    <span>Why interested in this position?</span>
                    <p><?php echo !empty($app['why_interested']) ? nl2br(htmlspecialchars($app['why_interested'])) : '—'; ?></p>
                </div>
                <div class="profile-q">
                    <span>Why join CPVIA?</span>
                    <p><?php echo !empty($app['why_cpvia']) ? nl2br(htmlspecialchars($app['why_cpvia'])) : '—'; ?></p>
                </div>
                <div class="profile-q">
                    <span>Willing to relocate?</span>
                    <p><?php echo !empty($app['willing_to_relocate']) ? 'Yes' : 'No'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar: Documents + Status + Actions -->
    <aside class="profile-side">
        <!-- Section 6: Resume & Documents -->
        <div class="profile-card">
            <h3 class="profile-section-title">Resume &amp; Documents</h3>
            <div class="profile-doc">
                <div class="profile-doc-name">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    <span><?php echo dv($resume_original); ?></span>
                </div>
                <?php if ($resume_url): ?>
                <div class="profile-doc-actions">
                    <a href="<?php echo htmlspecialchars($resume_url); ?>" target="_blank" rel="noopener" class="btn-download">View Resume</a>
                    <a href="<?php echo htmlspecialchars($resume_url); ?>" download class="btn-outline-pill">Download</a>
                </div>
                <?php else: ?>
                    <p class="profile-empty">No resume on file.</p>
                <?php endif; ?>
            </div>

            <div class="profile-links">
                <?php if ($cover_url): ?><a href="<?php echo htmlspecialchars($cover_url); ?>" target="_blank" rel="noopener" class="profile-link">Cover Letter</a><?php endif; ?>
                <?php if (!empty($app['portfolio_url'])): ?><a href="<?php echo htmlspecialchars($app['portfolio_url']); ?>" target="_blank" rel="noopener" class="profile-link">Portfolio</a><?php endif; ?>
                <?php if (!empty($app['linkedin_profile'])): ?><a href="<?php echo htmlspecialchars($app['linkedin_profile']); ?>" target="_blank" rel="noopener" class="profile-link">LinkedIn</a><?php endif; ?>
            </div>
        </div>

        <!-- Section 8: Application Status -->
        <div class="profile-card">
            <h3 class="profile-section-title">Application Status</h3>
            <div class="profile-status-current">
                <span class="status-badge <?php echo htmlspecialchars(status_class($current_status)); ?>"><?php echo htmlspecialchars($current_status); ?></span>
            </div>
            <form action="update_status.php" method="POST" class="profile-status-form">
                <input type="hidden" name="id" value="<?php echo (int) $app['id']; ?>">
                <input type="hidden" name="redirect" value="details">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">
                <label for="status">Change status</label>
                <select name="status" id="status">
                    <?php foreach ($status_options as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $current_status === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary-pill">Update Status</button>
            </form>
        </div>

        <!-- Section 9: Actions -->
        <div class="profile-card">
            <h3 class="profile-section-title">Actions</h3>
            <div class="profile-actions">
                <a href="applications.php" class="btn-outline-pill">Back to Applications</a>
                <?php if ($resume_url): ?><a href="<?php echo htmlspecialchars($resume_url); ?>" download class="btn-outline-pill">Download Resume</a><?php endif; ?>
                <form action="delete_application.php" method="POST" onsubmit="return confirm('Delete this application?\nThis cannot be undone.');">
                    <input type="hidden" name="id" value="<?php echo (int) $app['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">
                    <button type="submit" class="btn-delete">Delete Application</button>
                </form>
            </div>
        </div>
    </aside>
</div>

<?php include __DIR__ . '/partials/layout_bottom.php'; ?>
