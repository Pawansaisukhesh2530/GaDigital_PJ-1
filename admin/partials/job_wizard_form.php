<?php
/**
 * Shared 8-Step Job Wizard markup — used by BOTH Add Job and Edit Job.
 *
 * Expected variables (set by the including page):
 *   $values                array   field values (defaults or prefilled)
 *   $opts                  array   cpvia_job_option_lists()
 *   $skills_json           string  JSON of all skills
 *   $required_json         string  JSON of preselected required skill IDs
 *   $preferred_json        string  JSON of preselected preferred skill IDs
 *   $wizard_form_action    string  form POST target
 *   $wizard_is_edit        bool
 *   $wizard_job_id         int     (edit only)
 *   $wizard_publish_label  string  primary button label
 *   $wizard_draft_label    string  draft button label
 *   $wizard_context        ?array  ['title','job_code','status'] for the edit banner
 */

$employment_types = $opts['employment_types'];
$work_modes = $opts['work_modes'];
$priorities = $opts['priorities'];
$salary_types = $opts['salary_types'];
$currencies = $opts['currencies'];
$qualifications = $opts['qualifications'];
$genders = $opts['genders'];

$wizard_is_edit = $wizard_is_edit ?? false;
$wizard_form_action = $wizard_form_action ?? '';
$wizard_publish_label = $wizard_publish_label ?? 'Publish Job';
$wizard_draft_label = $wizard_draft_label ?? 'Save Draft';
$wizard_context = $wizard_context ?? null;
?>

<?php if ($wizard_context): ?>
<div class="wizard-context-banner">
    <div class="wizard-context-main">
        <span class="wizard-context-eyebrow"><?php echo $wizard_is_edit ? 'Editing Job' : 'New Job'; ?></span>
        <h2><?php echo htmlspecialchars($wizard_context['title'] !== '' ? $wizard_context['title'] : 'Untitled Job'); ?></h2>
    </div>
    <div class="wizard-context-meta">
        <?php if (!empty($wizard_context['job_code'])): ?>
            <span class="wizard-context-pill">Job Code: <?php echo htmlspecialchars($wizard_context['job_code']); ?></span>
        <?php endif; ?>
        <?php if (!empty($wizard_context['status'])): ?>
            <span class="status-badge <?php echo htmlspecialchars('job-status-' . strtolower(str_replace(' ', '-', $wizard_context['status']))); ?>"><?php echo htmlspecialchars($wizard_context['status']); ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="wizard" id="jobWizard">
    <!-- Progress indicator -->
    <div class="wizard-progress-card">
        <div class="wizard-progress-head">
            <span class="wizard-step-count">Step <span id="wizStepNum">1</span> of 8</span>
            <span class="wizard-step-name" id="wizStepName">Basic Job Details</span>
        </div>
        <div class="wizard-progress-track">
            <div class="wizard-progress-fill" id="wizProgressFill"></div>
        </div>
        <ol class="wizard-steps" id="wizardSteps">
            <?php
            $step_labels = ['Basic Details', 'Location', 'Experience', 'Salary', 'Description', 'Responsibilities', 'Benefits', 'Review'];
            foreach ($step_labels as $i => $label):
            ?>
            <li class="wizard-step-item<?php echo $i === 0 ? ' is-active' : ''; ?>" data-step="<?php echo $i + 1; ?>">
                <span class="wizard-step-dot"><span class="dot-num"><?php echo $i + 1; ?></span><span class="dot-check">&#10003;</span></span>
                <span class="wizard-step-label"><?php echo htmlspecialchars($label); ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <form method="POST" action="<?php echo htmlspecialchars($wizard_form_action); ?>" id="jobWizardForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(cpvia_csrf_token()); ?>">
        <input type="hidden" name="action" id="wizardAction" value="draft">
        <?php if ($wizard_is_edit): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $wizard_job_id); ?>">
        <?php endif; ?>
        <input type="hidden" name="required_skills" id="requiredSkillsInput" value="">
        <input type="hidden" name="preferred_skills" id="preferredSkillsInput" value="">
        <input type="hidden" name="description" id="descriptionInput">
        <input type="hidden" name="responsibilities" id="responsibilitiesInput">
        <input type="hidden" name="requirements" id="requirementsInput">
        <input type="hidden" name="benefits" id="benefitsInput">

        <!-- ============ STEP 1: Basic Job Details ============ -->
        <section class="wizard-panel form-section-card is-active" data-panel="1">
            <h3>Basic Job Details</h3>
            <p class="form-section-sub">Core information candidates see first.</p>

            <div class="form-group">
                <label for="title">Job Title <span class="req">*</span></label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($values['title']); ?>" data-required aria-describedby="hint-title" placeholder="e.g. Senior Clinical SAS Programmer">
                <small class="field-hint" id="hint-title">The public-facing title shown on the careers page.</small>
                <small class="field-error" data-error-for="title"></small>
            </div>

            <div class="wiz-grid-3">
                <div class="form-group">
                    <label for="department">Department <span class="req">*</span></label>
                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($values['department']); ?>" data-required-publish aria-describedby="hint-department" placeholder="e.g. Biometrics">
                    <small class="field-hint" id="hint-department">Team or function this role belongs to.</small>
                    <small class="field-error" data-error-for="department"></small>
                </div>
                <div class="form-group">
                    <label for="job_code">Job Code</label>
                    <input type="text" id="job_code" name="job_code" value="<?php echo htmlspecialchars($values['job_code']); ?>" aria-describedby="hint-job_code" placeholder="e.g. CPV-2026-014">
                    <small class="field-hint" id="hint-job_code">Optional internal reference code. Must be unique.</small>
                    <small class="field-error" data-error-for="job_code"></small>
                </div>
                <div class="form-group">
                    <label for="employment_type">Employment Type <span class="req">*</span></label>
                    <select id="employment_type" name="employment_type" data-required-publish aria-describedby="hint-employment_type">
                        <?php foreach ($employment_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $values['employment_type'] === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint" id="hint-employment_type">How the role is contracted.</small>
                    <small class="field-error" data-error-for="employment_type"></small>
                </div>
            </div>

            <div class="wiz-grid-3">
                <div class="form-group">
                    <label for="work_mode">Work Mode</label>
                    <select id="work_mode" name="work_mode" aria-describedby="hint-work_mode">
                        <?php foreach ($work_modes as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $values['work_mode'] === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint" id="hint-work_mode">Where the work is performed.</small>
                </div>
                <div class="form-group">
                    <label for="number_of_openings">Number of Openings</label>
                    <input type="number" min="1" step="1" id="number_of_openings" name="number_of_openings" value="<?php echo htmlspecialchars($values['number_of_openings']); ?>" aria-describedby="hint-openings">
                    <small class="field-hint" id="hint-openings">How many positions are available.</small>
                    <small class="field-error" data-error-for="number_of_openings"></small>
                </div>
                <div class="form-group">
                    <label for="hiring_priority">Hiring Priority</label>
                    <select id="hiring_priority" name="hiring_priority" aria-describedby="hint-priority">
                        <?php foreach ($priorities as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $values['hiring_priority'] === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint" id="hint-priority">Signals urgency internally.</small>
                </div>
            </div>
        </section>

        <!-- ============ STEP 2: Location ============ -->
        <section class="wizard-panel form-section-card" data-panel="2">
            <h3>Location</h3>
            <p class="form-section-sub">Where this role is based.</p>

            <div class="wiz-grid-3">
                <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($values['country']); ?>" aria-describedby="hint-country" placeholder="e.g. India">
                    <small class="field-hint" id="hint-country">Country where the job is located.</small>
                </div>
                <div class="form-group">
                    <label for="state">State / Region</label>
                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($values['state']); ?>" aria-describedby="hint-state" placeholder="e.g. Telangana">
                    <small class="field-hint" id="hint-state">State or province.</small>
                </div>
                <div class="form-group">
                    <label for="city">City <span class="req">*</span></label>
                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($values['city']); ?>" data-required-publish aria-describedby="hint-city" placeholder="e.g. Hyderabad">
                    <small class="field-hint" id="hint-city">Primary city shown on the careers listing.</small>
                    <small class="field-error" data-error-for="city"></small>
                </div>
            </div>

            <div class="wiz-grid-2">
                <div class="form-group">
                    <label for="office_location">Office Location</label>
                    <input type="text" id="office_location" name="office_location" value="<?php echo htmlspecialchars($values['office_location']); ?>" aria-describedby="hint-office" placeholder="e.g. HITEC City Campus">
                    <small class="field-hint" id="hint-office">Specific office / building, if any.</small>
                </div>
                <div class="form-group">
                    <label>Remote Option</label>
                    <label class="switch-field" for="remote_available">
                        <input type="checkbox" id="remote_available" name="remote_available" value="1" <?php echo $values['remote_available'] ? 'checked' : ''; ?>>
                        <span class="switch-track"><span class="switch-thumb"></span></span>
                        <span class="switch-text">Remote work available</span>
                    </label>
                    <small class="field-hint">Enable if candidates can work remotely for this role.</small>
                </div>
            </div>
        </section>

        <!-- ============ STEP 3: Experience & Education ============ -->
        <section class="wizard-panel form-section-card" data-panel="3">
            <h3>Experience &amp; Education</h3>
            <p class="form-section-sub">Eligibility expectations for applicants.</p>

            <div class="wizard-subhead">Experience</div>
            <div class="wiz-grid-2">
                <div class="form-group">
                    <label for="min_experience">Minimum Experience (years)</label>
                    <input type="number" min="0" step="0.5" id="min_experience" name="min_experience" value="<?php echo htmlspecialchars($values['min_experience']); ?>" aria-describedby="hint-minexp">
                    <small class="field-hint" id="hint-minexp">Lowest acceptable years of experience.</small>
                    <small class="field-error" data-error-for="min_experience"></small>
                </div>
                <div class="form-group">
                    <label for="max_experience">Maximum Experience (years)</label>
                    <input type="number" min="0" step="0.5" id="max_experience" name="max_experience" value="<?php echo htmlspecialchars($values['max_experience']); ?>" aria-describedby="hint-maxexp">
                    <small class="field-hint" id="hint-maxexp">Upper bound, if any.</small>
                    <small class="field-error" data-error-for="max_experience"></small>
                </div>
            </div>

            <div class="wizard-subhead">Education</div>
            <div class="wiz-grid-3">
                <div class="form-group">
                    <label for="minimum_qualification">Minimum Qualification</label>
                    <select id="minimum_qualification" name="minimum_qualification" aria-describedby="hint-minqual">
                        <option value="">— Select —</option>
                        <?php foreach ($qualifications as $q): ?>
                            <option value="<?php echo htmlspecialchars($q); ?>" <?php echo $values['minimum_qualification'] === $q ? 'selected' : ''; ?>><?php echo htmlspecialchars($q); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint" id="hint-minqual">Lowest qualification level accepted.</small>
                </div>
                <div class="form-group">
                    <label for="degree">Degree</label>
                    <input type="text" id="degree" name="degree" value="<?php echo htmlspecialchars($values['degree']); ?>" aria-describedby="hint-degree" placeholder="e.g. B.Pharm, M.Sc Statistics">
                    <small class="field-hint" id="hint-degree">Preferred degree(s).</small>
                </div>
                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($values['specialization']); ?>" aria-describedby="hint-spec" placeholder="e.g. Biostatistics">
                    <small class="field-hint" id="hint-spec">Field of study, if relevant.</small>
                </div>
            </div>
        </section>

        <!-- ============ STEP 4: Salary & Skills ============ -->
        <section class="wizard-panel form-section-card" data-panel="4">
            <h3>Salary &amp; Skills</h3>
            <p class="form-section-sub">Compensation range and required expertise.</p>

            <div class="wizard-subhead">Salary</div>
            <div class="wiz-grid-4">
                <div class="form-group">
                    <label for="salary_type">Salary Type</label>
                    <select id="salary_type" name="salary_type" aria-describedby="hint-saltype">
                        <?php foreach ($salary_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $values['salary_type'] === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint" id="hint-saltype">Basis of the salary range.</small>
                </div>
                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select id="currency" name="currency" aria-describedby="hint-currency">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $values['currency'] === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint" id="hint-currency">Currency for the amounts below.</small>
                </div>
                <div class="form-group">
                    <label for="min_salary">Minimum Salary</label>
                    <input type="number" min="0" step="1" id="min_salary" name="min_salary" value="<?php echo htmlspecialchars($values['min_salary']); ?>" aria-describedby="hint-minsal">
                    <small class="field-hint" id="hint-minsal">Lower end of the offered range.</small>
                    <small class="field-error" data-error-for="min_salary"></small>
                </div>
                <div class="form-group">
                    <label for="max_salary">Maximum Salary</label>
                    <input type="number" min="0" step="1" id="max_salary" name="max_salary" value="<?php echo htmlspecialchars($values['max_salary']); ?>" aria-describedby="hint-maxsal">
                    <small class="field-hint" id="hint-maxsal">Upper end of the offered range.</small>
                    <small class="field-error" data-error-for="max_salary"></small>
                </div>
            </div>

            <div class="wiz-grid-2">
                <div class="skill-picker" data-skill-target="requiredSkillsInput" data-skill-type="required">
                    <div class="wizard-subhead">Required Skills</div>
                    <div class="skill-search-wrap">
                        <input type="text" class="skill-search" placeholder="Search skills to add as required…" aria-label="Search required skills" autocomplete="off">
                        <div class="skill-dropdown" role="listbox"></div>
                    </div>
                    <div class="skill-chips" aria-live="polite"></div>
                    <small class="field-hint">Type to search the skills library, then click to add. Stored as <strong>required</strong>.</small>
                </div>

                <div class="skill-picker" data-skill-target="preferredSkillsInput" data-skill-type="preferred">
                    <div class="wizard-subhead">Preferred Skills</div>
                    <div class="skill-search-wrap">
                        <input type="text" class="skill-search" placeholder="Search skills to add as preferred…" aria-label="Search preferred skills" autocomplete="off">
                        <div class="skill-dropdown" role="listbox"></div>
                    </div>
                    <div class="skill-chips" aria-live="polite"></div>
                    <small class="field-hint">Nice-to-have skills. Stored as <strong>preferred</strong>. A skill already chosen as required cannot be added here.</small>
                </div>
            </div>
        </section>

        <!-- ============ STEP 5: Job Description ============ -->
        <section class="wizard-panel form-section-card" data-panel="5">
            <h3>Job Description</h3>
            <p class="form-section-sub">Describe the role, team, and impact. <span class="req">*</span> required to publish.</p>

            <div class="form-group">
                <label id="lbl-description">Description <span class="req">*</span></label>
                <div class="rte" data-rte-target="descriptionInput" aria-labelledby="lbl-description">
                    <div class="rte-toolbar" role="toolbar" aria-label="Formatting"></div>
                    <div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Write a compelling job description…"><?php echo $values['description']; ?></div>
                </div>
                <small class="field-hint">Use bold, italic, lists and links to keep it scannable.</small>
                <small class="field-error" data-error-for="description"></small>
            </div>
        </section>

        <!-- ============ STEP 6: Responsibilities & Requirements ============ -->
        <section class="wizard-panel form-section-card" data-panel="6">
            <h3>Responsibilities &amp; Requirements</h3>
            <p class="form-section-sub">What they will do and what they must bring.</p>

            <div class="form-group">
                <label id="lbl-responsibilities">Responsibilities</label>
                <div class="rte" data-rte-target="responsibilitiesInput" aria-labelledby="lbl-responsibilities">
                    <div class="rte-toolbar" role="toolbar" aria-label="Formatting"></div>
                    <div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="List the key responsibilities…"><?php echo $values['responsibilities']; ?></div>
                </div>
                <small class="field-hint">A bulleted list works best here.</small>
            </div>

            <div class="form-group">
                <label id="lbl-requirements">Requirements <span class="req">*</span></label>
                <div class="rte" data-rte-target="requirementsInput" aria-labelledby="lbl-requirements">
                    <div class="rte-toolbar" role="toolbar" aria-label="Formatting"></div>
                    <div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="List the must-have requirements…"><?php echo $values['requirements']; ?></div>
                </div>
                <small class="field-hint">Qualifications, skills and experience needed. Required to publish.</small>
                <small class="field-error" data-error-for="requirements"></small>
            </div>
        </section>

        <!-- ============ STEP 7: Benefits & Candidate Preferences ============ -->
        <section class="wizard-panel form-section-card" data-panel="7">
            <h3>Benefits &amp; Candidate Preferences</h3>
            <p class="form-section-sub">Perks and any candidate preferences.</p>

            <div class="form-group">
                <label id="lbl-benefits">Benefits</label>
                <div class="rte" data-rte-target="benefitsInput" aria-labelledby="lbl-benefits">
                    <div class="rte-toolbar" role="toolbar" aria-label="Formatting"></div>
                    <div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Health insurance, learning budget, flexible hours…"><?php echo $values['benefits']; ?></div>
                </div>
                <small class="field-hint">Highlight what makes CPVIA a great place to work.</small>
            </div>

            <div class="wizard-subhead">Candidate Preferences</div>
            <div class="wiz-grid-4">
                <div class="form-group">
                    <label for="preferred_notice_period">Notice Period</label>
                    <input type="text" id="preferred_notice_period" name="preferred_notice_period" value="<?php echo htmlspecialchars($values['preferred_notice_period']); ?>" aria-describedby="hint-notice" placeholder="e.g. 30 days or Immediate">
                    <small class="field-hint" id="hint-notice">Preferred availability to join.</small>
                </div>
                <div class="form-group">
                    <label for="gender_preference">Gender Preference</label>
                    <select id="gender_preference" name="gender_preference" aria-describedby="hint-gender">
                        <?php foreach ($genders as $g): ?>
                            <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $values['gender_preference'] === $g ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint" id="hint-gender">Leave as "Any" unless legally justified.</small>
                </div>
                <div class="form-group">
                    <label for="minimum_age">Minimum Age</label>
                    <input type="number" min="16" max="100" step="1" id="minimum_age" name="minimum_age" value="<?php echo htmlspecialchars($values['minimum_age']); ?>" aria-describedby="hint-minage">
                    <small class="field-hint" id="hint-minage">Optional lower age limit.</small>
                    <small class="field-error" data-error-for="minimum_age"></small>
                </div>
                <div class="form-group">
                    <label for="maximum_age">Maximum Age</label>
                    <input type="number" min="16" max="100" step="1" id="maximum_age" name="maximum_age" value="<?php echo htmlspecialchars($values['maximum_age']); ?>" aria-describedby="hint-maxage">
                    <small class="field-hint" id="hint-maxage">Optional upper age limit.</small>
                    <small class="field-error" data-error-for="maximum_age"></small>
                </div>
            </div>

            <?php
            $sub_mode = in_array($values['submission_mode'] ?? '', ['BACKEND_ONLY', 'EMAIL_ONLY', 'BACKEND_AND_EMAIL'], true)
                ? $values['submission_mode'] : 'BACKEND_ONLY';
            $needs_email = in_array($sub_mode, ['EMAIL_ONLY', 'BACKEND_AND_EMAIL'], true);
            ?>
            <div class="wizard-subhead">Application Delivery</div>
            <p class="form-section-sub">Choose how applications for <strong>this job</strong> are received. SMTP is configured once in <a href="settings.php">Settings</a>.</p>

            <div class="form-group">
                <label>Receive Applications Via <span class="req">*</span></label>
                <div class="delivery-options" id="deliveryOptions">
                    <label class="delivery-option">
                        <input type="radio" name="submission_mode" value="BACKEND_ONLY" <?php echo $sub_mode === 'BACKEND_ONLY' ? 'checked' : ''; ?>>
                        <span class="delivery-option-body">
                            <span class="delivery-option-title">Backend Dashboard Only</span>
                            <span class="delivery-option-desc">Applications are saved to the admin dashboard. No email is sent.</span>
                        </span>
                    </label>
                    <label class="delivery-option">
                        <input type="radio" name="submission_mode" value="EMAIL_ONLY" <?php echo $sub_mode === 'EMAIL_ONLY' ? 'checked' : ''; ?>>
                        <span class="delivery-option-body">
                            <span class="delivery-option-title">Email Only</span>
                            <span class="delivery-option-desc">Applications are emailed to your recipients. Nothing is stored in the dashboard.</span>
                        </span>
                    </label>
                    <label class="delivery-option">
                        <input type="radio" name="submission_mode" value="BACKEND_AND_EMAIL" <?php echo $sub_mode === 'BACKEND_AND_EMAIL' ? 'checked' : ''; ?>>
                        <span class="delivery-option-body">
                            <span class="delivery-option-title">Backend Dashboard + Email</span>
                            <span class="delivery-option-desc">Applications are saved to the dashboard and emailed to your recipients.</span>
                        </span>
                    </label>
                </div>
                <small class="field-error" data-error-for="submission_mode"></small>
            </div>

            <div class="form-group" id="recipientEmailsGroup" style="<?php echo $needs_email ? '' : 'display:none;'; ?>">
                <label for="recipient_emails">Recipient Email(s) <span class="req">*</span></label>
                <textarea id="recipient_emails" name="recipient_emails" rows="2" aria-describedby="hint-recipients" placeholder="hr@company.com, manager@company.com"><?php echo htmlspecialchars($values['recipient_emails'] ?? ''); ?></textarea>
                <small class="field-hint" id="hint-recipients">One or more addresses separated by commas. All applications for this job are sent here.</small>
                <small class="field-error" data-error-for="recipient_emails"></small>
            </div>
        </section>

        <!-- ============ STEP 8: Review ============ -->
        <section class="wizard-panel form-section-card" data-panel="8">
            <h3>Review &amp; <?php echo $wizard_is_edit ? 'Update' : 'Publish'; ?></h3>
            <p class="form-section-sub"><?php echo $wizard_is_edit ? 'Check your changes below, then update the job.' : 'Check everything below, then save a draft or publish.'; ?></p>
            <div class="review-grid" id="reviewGrid"><!-- populated by JS --></div>
        </section>

        <!-- ============ Navigation ============ -->
        <div class="wizard-nav">
            <button type="button" class="btn-cancel wizard-btn" id="wizPrev">Previous</button>
            <div class="wizard-nav-right">
                <a href="jobs.php" class="wizard-link-cancel">Cancel</a>
                <button type="button" class="btn-outline-pill wizard-btn" id="wizSaveDraft"><?php echo htmlspecialchars($wizard_draft_label); ?></button>
                <button type="button" class="btn-primary-pill wizard-btn" id="wizNext">Next</button>
                <button type="button" class="btn-primary-pill wizard-btn" id="wizPublish" style="display:none;"><?php echo htmlspecialchars($wizard_publish_label); ?></button>
            </div>
        </div>
    </form>
</div>

<script>
    window.CPVIA_SKILLS = <?php echo $skills_json ?: '[]'; ?>;
    window.CPVIA_REQUIRED = <?php echo $required_json ?: '[]'; ?>;
    window.CPVIA_PREFERRED = <?php echo $preferred_json ?: '[]'; ?>;
</script>
<script src="assets/job_wizard.js"></script>
