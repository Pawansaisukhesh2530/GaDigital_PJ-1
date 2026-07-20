<?php
require_once __DIR__ . '/db.php';

$job = 'General Application';
$job_id = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$db_file = __DIR__ . '/admin/cpvia_database.sqlite';

$job_details = null;
if ($job_id > 0) {
    try {
        $pdo = cpvia_db($db_file);

        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
        $stmt->execute([$job_id]);
        $found_job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($found_job) {
            $job = $found_job['title'];
            $job_details = $found_job;
        }
    } catch (Exception $e) {
        // Fall back to the default job title below if the lookup fails.
    }
}

$job = htmlspecialchars($job);
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_title = htmlspecialchars($_POST['job_title']);
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $cover_letter = htmlspecialchars($_POST['cover_letter']);
    
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/resumes/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['resume']['name']));
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_path)) {
            try {
                $pdo = cpvia_db($db_file);

                $status = "New";
                $stmt = $pdo->prepare("
                    INSERT INTO applications
                    (job_title, name, email, phone, cover_letter, resume_path, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $job_title,
                    $name,
                    $email,
                    $phone,
                    $cover_letter,
                    $filename,
                    $status
                ]);
                $success = true;
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Failed to upload resume.";
        }
    } else {
        $error = "Please upload a valid resume.";
    }
}
include 'header.php'; 
?>

<style>
.apply-page {
    background: radial-gradient(circle at 15% 20%, #F4F2FF 0%, #FAFAFF 55%, #FFF6F0 100%);
    padding: 7.5rem 5% 5rem;
}

.apply-breadcrumb {
    max-width: 1400px;
    margin: 0 auto 2rem;
}

.apply-breadcrumb a {
    color: var(--text-light);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: color 0.2s;
}

.apply-breadcrumb a:hover { color: var(--primary-blue); }

.apply-layout {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 2fr 3fr;
    gap: 2.5rem;
    align-items: start;
}

/* ---------- LEFT: Job Information ---------- */
.job-info-panel {
    background: #fff;
    border-radius: 20px;
    border: 1px solid #EDEAF8;
    box-shadow: 0 12px 40px rgba(61, 26, 138, 0.06);
    padding: 2.5rem;
    position: sticky;
    top: 110px;
}

.job-info-eyebrow {
    background: rgba(61, 26, 138, 0.06);
    color: var(--primary-blue);
    padding: 0.35rem 0.9rem;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    display: inline-block;
    margin-bottom: 1rem;
}

.job-info-panel h1 {
    font-size: 1.7rem !important;
    color: var(--primary-blue);
    font-weight: 800;
    line-height: 1.25;
    margin-bottom: 1.2rem;
}

.job-info-meta {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    margin-bottom: 1.8rem;
    padding-bottom: 1.8rem;
    border-bottom: 1px solid #F0EBF7;
}

.job-info-meta-row {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    font-size: 0.92rem;
    color: var(--text-dark);
    font-weight: 600;
}

.job-info-meta-row .meta-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #F0EBF7;
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.job-info-section {
    margin-bottom: 1.6rem;
}

.job-info-section:last-of-type { margin-bottom: 0; }

.job-info-section h4 {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--primary-orange);
    font-weight: 800;
    margin-bottom: 0.6rem;
}

.job-info-section p {
    color: var(--text-light);
    font-size: 0.92rem;
    line-height: 1.7;
    margin: 0;
    white-space: pre-line;
}

.job-info-tips {
    background: #F4F2FF;
    border-radius: 14px;
    padding: 1.3rem 1.4rem;
    margin-top: 1.8rem;
}

.job-info-tips h4 {
    color: var(--primary-blue);
    font-size: 0.85rem;
    font-weight: 800;
    margin-bottom: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.job-info-tips ul {
    margin: 0;
    padding-left: 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.job-info-tips li {
    color: var(--text-light);
    font-size: 0.85rem;
    line-height: 1.5;
}

.job-info-company {
    margin-top: 1.8rem;
    padding-top: 1.8rem;
    border-top: 1px solid #F0EBF7;
    display: flex;
    align-items: center;
    gap: 0.9rem;
}

.job-info-company .company-logo-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--primary-blue);
    color: #fff;
    font-weight: 800;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.job-info-company strong {
    display: block;
    color: var(--primary-blue);
    font-size: 0.9rem;
    margin-bottom: 0.15rem;
}

.job-info-company span {
    color: var(--text-light);
    font-size: 0.78rem;
}

/* ---------- RIGHT: Application Form ---------- */
.apply-form-panel {
    background: #fff;
    border-radius: 20px;
    border: 1px solid #EDEAF8;
    box-shadow: 0 12px 40px rgba(61, 26, 138, 0.06);
    padding: 3rem;
}

.apply-form-panel h2 {
    font-size: 1.6rem !important;
    color: var(--primary-blue);
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.apply-form-sub {
    color: var(--text-light);
    font-size: 0.95rem;
    margin-bottom: 2.2rem;
}

.apply-form {
    display: flex;
    flex-direction: column;
    gap: 1.6rem;
}

.apply-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.6rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.6rem;
    font-weight: 700;
    color: var(--primary-blue);
    font-size: 0.85rem;
    letter-spacing: 0.2px;
}

.form-group .optional-tag {
    color: #aaa;
    font-weight: 500;
    text-transform: none;
    letter-spacing: 0;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group textarea {
    width: 100%;
    padding: 1rem 1.1rem;
    border: 1.5px solid #EDEAF8;
    border-radius: 12px;
    font-family: inherit;
    font-size: 0.95rem;
    color: var(--text-dark);
    background: #fdfdfd;
    transition: border-color 0.3s, box-shadow 0.3s, background 0.3s;
}

.form-group input[type="text"]:focus,
.form-group input[type="email"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-orange);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(255, 85, 0, 0.08);
}

.form-group textarea {
    resize: vertical;
    line-height: 1.6;
}

/* File upload */
.file-upload-wrap {
    position: relative;
}

.file-upload-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

.file-upload-dropzone {
    border: 1.5px dashed #D8CFF2;
    border-radius: 12px;
    background: #FAFAFF;
    padding: 1.6rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: border-color 0.3s, background 0.3s;
}

.file-upload-wrap:hover .file-upload-dropzone,
.file-upload-wrap.has-file .file-upload-dropzone {
    border-color: var(--primary-orange);
    background: #FFF6F0;
}

.file-upload-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #F0EBF7;
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.file-upload-text strong {
    display: block;
    color: var(--primary-blue);
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
}

.file-upload-text span {
    color: var(--text-light);
    font-size: 0.78rem;
}

.file-upload-filename {
    color: var(--primary-orange);
    font-weight: 700;
}

.apply-form-footer {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 0.4rem;
}

.privacy-note {
    color: #aaa;
    font-size: 0.78rem;
    line-height: 1.5;
}

.btn-submit-apply {
    background: var(--primary-orange);
    color: #fff;
    padding: 1.15rem 2rem;
    border: none;
    border-radius: 14px;
    font-weight: 700;
    width: 100%;
    cursor: pointer;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    transition: background 0.3s, transform 0.2s, box-shadow 0.3s;
    box-shadow: 0 10px 25px rgba(255, 85, 0, 0.25);
}

.btn-submit-apply:hover {
    background: #e04a00;
    transform: translateY(-2px);
    box-shadow: 0 14px 32px rgba(255, 85, 0, 0.3);
}

/* ---------- Alerts ---------- */
.apply-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1.1rem 1.3rem;
    border-radius: 14px;
    margin-bottom: 1.8rem;
    font-size: 0.9rem;
    line-height: 1.5;
}

.apply-alert .alert-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.apply-alert-error {
    background: #FDE8E8;
    color: #B91C1C;
    border: 1px solid #F5C2C2;
}
.apply-alert-error .alert-icon { background: #F5C2C2; color: #B91C1C; }

/* ---------- Success Card ---------- */
.apply-success-panel {
    background: #fff;
    border-radius: 20px;
    border: 1px solid #EDEAF8;
    box-shadow: 0 12px 40px rgba(61, 26, 138, 0.06);
    padding: 4rem 3rem;
    text-align: center;
    grid-column: 1 / -1;
}

.apply-success-icon {
    width: 74px;
    height: 74px;
    border-radius: 50%;
    background: #e6f7eb;
    color: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.6rem;
}

.apply-success-panel h2 {
    font-size: 1.8rem !important;
    color: var(--primary-blue);
    font-weight: 800;
    margin-bottom: 0.7rem;
}

.apply-success-panel p {
    color: var(--text-light);
    font-size: 1rem;
    max-width: 420px;
    margin: 0 auto 2rem;
    line-height: 1.6;
}

.btn-back-careers {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary-blue);
    color: #fff;
    text-decoration: none;
    padding: 0.9rem 2rem;
    border-radius: 30px;
    font-weight: 700;
    font-size: 0.9rem;
    transition: background 0.3s, transform 0.2s;
}

.btn-back-careers:hover {
    background: #2a1160;
    transform: translateY(-2px);
}

/* ---------- Responsive ---------- */
@media (max-width: 1024px) {
    .apply-layout { grid-template-columns: 1fr; }
    .job-info-panel { position: static; }
}

@media (max-width: 768px) {
    .apply-page { padding: 6.5rem 6% 3.5rem; }
    .job-info-panel, .apply-form-panel { padding: 1.8rem; }
    .apply-form-row { grid-template-columns: 1fr; gap: 1.6rem; }
    .apply-success-panel { padding: 3rem 1.8rem; }
}

@media (max-width: 480px) {
    .apply-page { padding: 6rem 5% 3rem; }
    .job-info-panel, .apply-form-panel { padding: 1.5rem; border-radius: 16px; }
    .job-info-panel h1 { font-size: 1.4rem !important; }
    .apply-form-panel h2 { font-size: 1.35rem !important; }
}
</style>

<?php
$info_department = $job_details['department'] ?? '';
$info_location = $job_details['location'] ?? '';
$info_employment_type = $job_details['employment_type'] ?? '';
$info_description = $job_details['description'] ?? '';
$info_requirements = $job_details['requirements'] ?? '';
?>

<div class="apply-page">
    <div class="apply-breadcrumb">
        <a href="careers">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Careers
        </a>
    </div>

    <div class="apply-layout">
        <?php if ($success): ?>
            <div class="apply-success-panel">
                <div class="apply-success-icon">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h2>Application Submitted Successfully</h2>
                <p>Thank you for applying for <?php echo $job; ?>. We will review your resume and get back to you soon.</p>
                <a href="careers" class="btn-back-careers">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Back to Careers
                </a>
            </div>
        <?php else: ?>

            <!-- LEFT: Job Information -->
            <aside class="job-info-panel">
                <span class="job-info-eyebrow">Now Hiring</span>
                <h1><?php echo $job; ?></h1>

                <?php if ($info_department || $info_location || $info_employment_type): ?>
                <div class="job-info-meta">
                    <?php if ($info_department): ?>
                    <div class="job-info-meta-row">
                        <span class="meta-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                        </span>
                        <?php echo htmlspecialchars($info_department); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($info_location): ?>
                    <div class="job-info-meta-row">
                        <span class="meta-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        </span>
                        <?php echo htmlspecialchars($info_location); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($info_employment_type): ?>
                    <div class="job-info-meta-row">
                        <span class="meta-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                        </span>
                        <?php echo htmlspecialchars($info_employment_type); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($info_description): ?>
                <div class="job-info-section">
                    <h4>Job Description</h4>
                    <p><?php echo htmlspecialchars($info_description); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($info_requirements): ?>
                <div class="job-info-section">
                    <h4>Requirements</h4>
                    <p><?php echo htmlspecialchars($info_requirements); ?></p>
                </div>
                <?php endif; ?>

                <div class="job-info-tips">
                    <h4>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Application Tips
                    </h4>
                    <ul>
                        <li>Double-check your contact details before submitting.</li>
                        <li>Upload your most recent resume in PDF or DOCX format.</li>
                        <li>Use the cover letter to highlight relevant experience.</li>
                    </ul>
                </div>

                <div class="job-info-company">
                    <div class="company-logo-badge">CP</div>
                    <div>
                        <strong>CPVIA</strong>
                        <span>Clinical Research &amp; Biometrics</span>
                    </div>
                </div>
            </aside>

            <!-- RIGHT: Application Form -->
            <section class="apply-form-panel">
                <h2>Application Form</h2>
                <p class="apply-form-sub">Please fill out the form below and attach your resume.</p>

                <?php if ($error): ?>
                    <div class="apply-alert apply-alert-error">
                        <span class="alert-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        </span>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <form class="apply-form" action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="job_title" value="<?php echo $job; ?>">

                    <div class="apply-form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number <span class="optional-tag">(optional)</span></label>
                        <input type="text" id="phone" name="phone" placeholder="+1 (555) 000-0000">
                    </div>

                    <div class="form-group">
                        <label for="resume">Upload Resume (PDF, DOCX) *</label>
                        <div class="file-upload-wrap" id="resumeUploadWrap">
                            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" required class="file-upload-input">
                            <div class="file-upload-dropzone">
                                <span class="file-upload-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                </span>
                                <span class="file-upload-text" id="resumeUploadText">
                                    <strong>Click to upload your resume</strong>
                                    <span>PDF or DOCX, up to 10MB</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cover_letter">Cover Letter / Message <span class="optional-tag">(optional)</span></label>
                        <textarea id="cover_letter" name="cover_letter" rows="6" placeholder="Tell us why you're a great fit for this role..."></textarea>
                    </div>

                    <div class="apply-form-footer">
                        <p class="privacy-note">By submitting this application, you agree to let CPVIA store your details for recruitment purposes.</p>
                        <button type="submit" class="btn-submit-apply">
                            Submit Application
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var resumeInput = document.getElementById('resume');
    var uploadText = document.getElementById('resumeUploadText');
    var uploadWrap = document.getElementById('resumeUploadWrap');

    if (resumeInput && uploadText && uploadWrap) {
        resumeInput.addEventListener('change', function () {
            if (resumeInput.files && resumeInput.files.length > 0) {
                uploadText.innerHTML = '<strong>File selected</strong><span class="file-upload-filename">' + resumeInput.files[0].name + '</span>';
                uploadWrap.classList.add('has-file');
            } else {
                uploadText.innerHTML = '<strong>Click to upload your resume</strong><span>PDF or DOCX, up to 10MB</span>';
                uploadWrap.classList.remove('has-file');
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
