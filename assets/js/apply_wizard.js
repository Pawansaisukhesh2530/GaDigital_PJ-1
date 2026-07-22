/* =========================================================================
   CPVIA Candidate Application Wizard (public, 7 steps)
   Self-contained: step navigation, validation, skill chips, localStorage
   auto-save, file handling, review rendering, single-submit protection.
   Reads state from window.CPVIA_APPLY.
   ========================================================================= */
(function () {
    'use strict';

    var cfg = window.CPVIA_APPLY || {};
    var form = document.getElementById('applyForm');
    if (!form) { return; } // success / not-available page — nothing to wire.

    var TOTAL = 7;
    var NAMES = ['Resume Upload', 'Personal Information', 'Professional Information',
        'Education', 'Skills', 'Additional Questions', 'Review & Submit'];
    var STORAGE_KEY = 'cpvia_application_draft_' + (cfg.jobId || 0);

    var panels = form.querySelectorAll('.apply-panel');
    var stepItems = document.querySelectorAll('.apply-step-item');
    var fill = document.getElementById('apProgressFill');
    var stepNum = document.getElementById('apStepNum');
    var stepName = document.getElementById('apStepName');
    var btnPrev = document.getElementById('apPrev');
    var btnNext = document.getElementById('apNext');
    var btnSubmit = document.getElementById('apSubmit');

    var current = 1;
    var skills = cfg.skills || [];
    var skillById = {};
    skills.forEach(function (s) { skillById[s.id] = s.name; });
    var selectedSkills = [];

    /* ------------------------------------------------------------- helpers */
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function el(id) { return document.getElementById(id); }
    function val(id) { var e = el(id); return e ? e.value.trim() : ''; }
    function isEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
    function isUrl(v) { return /^https?:\/\/.+/i.test(v); }

    function setErr(field, msg) {
        var box = form.querySelector('.apply-err[data-error-for="' + field + '"]');
        if (box) { box.textContent = msg || ''; }
        var input = el(field);
        if (input) { input.classList.toggle('has-error', !!msg); }
    }
    function clearErrs(panel) {
        panel.querySelectorAll('.apply-err').forEach(function (b) { b.textContent = ''; });
        panel.querySelectorAll('.has-error').forEach(function (i) { i.classList.remove('has-error'); });
    }

    /* ------------------------------------------------------ step validation */
    function validateStep(n) {
        var panel = form.querySelector('.apply-panel[data-panel="' + n + '"]');
        clearErrs(panel);
        var ok = true;
        function fail(f, m) { setErr(f, m); ok = false; }

        if (n === 1) {
            var resume = el('resume');
            if (!resume || !resume.files || resume.files.length === 0) { fail('resume', 'Please attach your resume (PDF, DOC or DOCX).'); }
            if (val('portfolio_url') && !isUrl(val('portfolio_url'))) { fail('portfolio_url', 'Enter a valid URL or leave blank.'); }
        } else if (n === 2) {
            if (!val('full_name')) { fail('full_name', 'Full name is required.'); }
            if (!isEmail(val('email'))) { fail('email', 'Enter a valid email address.'); }
            if (!/^[0-9+\-\s()]{7,20}$/.test(val('mobile'))) { fail('mobile', 'Enter a valid mobile number.'); }
            if (!val('current_location')) { fail('current_location', 'Current location is required.'); }
            if (val('linkedin_profile') && !isUrl(val('linkedin_profile'))) { fail('linkedin_profile', 'Enter a valid http(s) URL or leave blank.'); }
        } else if (n === 3) {
            var te = parseFloat(val('total_experience'));
            var re = parseFloat(val('relevant_experience'));
            if (val('total_experience') === '' || isNaN(te) || te < 0 || te > 60) { fail('total_experience', 'Enter total experience (0–60).'); }
            if (val('relevant_experience') === '' || isNaN(re) || re < 0 || re > 60) { fail('relevant_experience', 'Enter relevant experience (0–60).'); }
            else if (!isNaN(te) && re > te) { fail('relevant_experience', 'Relevant cannot exceed total experience.'); }
            ['current_ctc', 'expected_ctc'].forEach(function (k) {
                if (val(k) !== '' && (parseFloat(val(k)) < 0 || isNaN(parseFloat(val(k))))) { fail(k, 'CTC cannot be negative.'); }
            });
            if (!val('employment_status')) { fail('employment_status', 'Select your employment status.'); }
            if (!val('notice_period')) { fail('notice_period', 'Notice period is required.'); }
        } else if (n === 4) {
            if (!val('qualification')) { fail('qualification', 'Select your highest qualification.'); }
            if (val('graduation_year') !== '') {
                var gy = parseInt(val('graduation_year'), 10);
                var maxY = new Date().getFullYear() + 6;
                if (isNaN(gy) || gy < 1950 || gy > maxY) { fail('graduation_year', 'Enter a valid year (1950–' + maxY + ').'); }
            }
        } else if (n === 6) {
            if (el('apRelocateInput').value !== '0' && el('apRelocateInput').value !== '1') {
                fail('willing_to_relocate', 'Please choose Yes or No.');
            }
        } else if (n === 7) {
            if (!el('declaration_accurate').checked) { fail('declaration_accurate', 'Required to submit.'); }
            if (!el('consent_data_storage').checked) { fail('consent_data_storage', 'Required to submit.'); }
        }
        return ok;
    }

    /* ------------------------------------------------------------ navigation */
    function show(n) {
        current = Math.max(1, Math.min(TOTAL, n));
        panels.forEach(function (p) { p.classList.toggle('is-active', +p.getAttribute('data-panel') === current); });
        stepItems.forEach(function (li) {
            var s = +li.getAttribute('data-step');
            li.classList.toggle('is-active', s === current);
            li.classList.toggle('is-complete', s < current);
        });
        if (fill) { fill.style.width = ((current - 1) / (TOTAL - 1) * 100) + '%'; }
        if (stepNum) { stepNum.textContent = current; }
        if (stepName) { stepName.textContent = NAMES[current - 1]; }
        btnPrev.style.visibility = current === 1 ? 'hidden' : 'visible';
        btnNext.style.display = current === TOTAL ? 'none' : '';
        btnSubmit.style.display = current === TOTAL ? '' : 'none';
        if (current === TOTAL) { buildReview(); }
        saveDraft();
        var wrap = document.querySelector('.apply-progress') || form;
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function next() { if (validateStep(current)) { show(current + 1); } }
    function prev() { show(current - 1); }

    /* ------------------------------------------------------------- skills */
    var searchEl = el('apSkillSearch');
    var dropEl = el('apSkillDropdown');
    var chipsEl = el('apSkillChips');
    var skillsHidden = el('apSkillsInput');

    function commitSkills() {
        skillsHidden.value = selectedSkills.join(',');
        chipsEl.innerHTML = '';
        selectedSkills.forEach(function (id) {
            var chip = document.createElement('span');
            chip.className = 'apply-chip';
            chip.innerHTML = esc(skillById[id]) + '<button type="button" class="apply-chip-x" aria-label="Remove">&times;</button>';
            chip.querySelector('.apply-chip-x').addEventListener('click', function () {
                selectedSkills = selectedSkills.filter(function (x) { return x !== id; });
                commitSkills(); saveDraft();
            });
            chipsEl.appendChild(chip);
        });
    }
    function renderDrop(q) {
        if (!dropEl) { return; }
        q = (q || '').toLowerCase().trim();
        dropEl.innerHTML = '';
        var matches = skills.filter(function (s) {
            return selectedSkills.indexOf(s.id) === -1 && (q === '' || s.name.toLowerCase().indexOf(q) !== -1);
        }).slice(0, 8);
        if (!matches.length) { dropEl.classList.remove('open'); return; }
        matches.forEach(function (s, i) {
            var o = document.createElement('div');
            o.className = 'apply-skill-option' + (i === 0 ? ' active' : '');
            o.textContent = s.name;
            o.addEventListener('mousedown', function (e) { e.preventDefault(); addSkill(s.id); });
            dropEl.appendChild(o);
        });
        dropEl.classList.add('open');
    }
    function addSkill(id) {
        if (selectedSkills.indexOf(id) === -1) { selectedSkills.push(id); commitSkills(); saveDraft(); }
        if (searchEl) { searchEl.value = ''; }
        renderDrop('');
        if (searchEl) { searchEl.focus(); }
    }
    if (searchEl) {
        searchEl.addEventListener('input', function () { renderDrop(searchEl.value); });
        searchEl.addEventListener('focus', function () { renderDrop(searchEl.value); });
        searchEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); var f = dropEl.querySelector('.apply-skill-option'); if (f) { f.dispatchEvent(new MouseEvent('mousedown')); } }
            else if (e.key === 'Escape') { dropEl.classList.remove('open'); }
        });
        document.addEventListener('click', function (e) {
            var picker = el('applySkillPicker');
            if (picker && !picker.contains(e.target)) { dropEl.classList.remove('open'); }
        });
    }

    /* --------------------------------------------------- relocate radios */
    var relocateInput = el('apRelocateInput');
    Array.prototype.forEach.call(document.getElementsByName('willing_to_relocate_ui'), function (r) {
        r.addEventListener('change', function () { relocateInput.value = r.value; setErr('willing_to_relocate', ''); saveDraft(); });
    });
    function syncRelocateRadios() {
        var v = relocateInput.value;
        Array.prototype.forEach.call(document.getElementsByName('willing_to_relocate_ui'), function (r) {
            r.checked = (r.value === v);
        });
    }

    /* --------------------------------------------------------- file inputs */
    form.querySelectorAll('.apply-file').forEach(function (box) {
        var input = box.querySelector('input[type=file]');
        var textStrong = box.querySelector('.apply-file-text strong');
        var defaultText = textStrong ? textStrong.textContent : '';
        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                box.classList.add('has-file');
                textStrong.textContent = input.files[0].name;
                if (input.id === 'resume') { setErr('resume', ''); }
            } else {
                box.classList.remove('has-file');
                textStrong.textContent = defaultText;
            }
        });
        // Drag & drop support.
        ['dragenter', 'dragover'].forEach(function (ev) {
            box.addEventListener(ev, function (e) { e.preventDefault(); box.classList.add('dragging'); });
        });
        ['dragleave', 'dragend'].forEach(function (ev) {
            box.addEventListener(ev, function () { box.classList.remove('dragging'); });
        });
        box.addEventListener('drop', function (e) {
            e.preventDefault();
            box.classList.remove('dragging');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                try { input.files = e.dataTransfer.files; } catch (err) { /* older browsers */ }
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    /* ---------------------------------------- public API for the optional
       resume auto-fill module (assets/js/apply_resume_ai.js).
       Additive and safe: existing candidate-entered values are never
       overwritten, unknown fields/skills are ignored, and every mutation
       reuses the existing draft auto-save. */
    window.CPVIAApplyWizard = {
        fieldExists: function (id) { return !!el(id); },
        getFieldValue: function (id) { return val(id); },
        isFieldEmpty: function (id) { var e = el(id); return !e || String(e.value).trim() === ''; },
        /** Fill a field ONLY when it is currently empty. Returns true if filled. */
        setFieldIfEmpty: function (id, value) {
            var e = el(id);
            if (!e) { return false; }
            if (String(e.value).trim() !== '') { return false; } // protect user data
            var v = (value == null) ? '' : String(value).trim();
            if (v === '') { return false; }
            if (e.tagName === 'SELECT') {
                var matched = false;
                Array.prototype.forEach.call(e.options, function (o) { if (o.value === v) { matched = true; } });
                if (!matched) { return false; } // never inject an invalid option
            }
            e.value = v;
            saveDraft();
            return true;
        },
        listSkills: function () { return skills.slice(); },
        isSkillSelected: function (id) { return selectedSkills.indexOf(Number(id)) !== -1; },
        selectSkillById: function (id) {
            id = Number(id);
            if (!skillById[id]) { return false; }
            if (selectedSkills.indexOf(id) === -1) { selectedSkills.push(id); commitSkills(); saveDraft(); }
            return true;
        },
        goToStep: function (n) { show(n); },
        saveDraft: function () { saveDraft(); }
    };

    /* ------------------------------------------------------- textarea count */
    form.querySelectorAll('textarea[maxlength]').forEach(function (ta) {
        var counter = form.querySelector('.apply-count[data-count-for="' + ta.id + '"]');
        function upd() { if (counter) { counter.textContent = ta.value.length + ' / ' + ta.getAttribute('maxlength'); } }
        ta.addEventListener('input', function () { upd(); saveDraft(); });
        upd();
    });

    /* ------------------------------------------------------------- review */
    function pv(id) { var e = el(id); if (!e) { return '—'; } if (e.tagName === 'SELECT') { return e.value ? e.options[e.selectedIndex].text : '—'; } return e.value.trim() || '—'; }
    function fileName(id) { var e = el(id); return (e && e.files && e.files.length) ? e.files[0].name : '—'; }
    function rows(list) {
        return list.map(function (r) { return '<div class="apply-review-row"><span>' + esc(r[0]) + '</span><span>' + (r[2] ? r[1] : esc(r[1])) + '</span></div>'; }).join('');
    }
    function card(title, step, body) {
        return '<div class="apply-review-card"><div class="apply-review-head"><h3>' + esc(title) +
            '</h3><button type="button" class="apply-review-edit" data-goto="' + step + '">Edit</button></div>' + body + '</div>';
    }
    function buildReview() {
        var review = el('applyReview');
        if (!review) { return; }
        var relocate = relocateInput.value === '1' ? 'Yes' : (relocateInput.value === '0' ? 'No' : '—');
        var skillNames = selectedSkills.length
            ? selectedSkills.map(function (id) { return '<span class="apply-review-chip">' + esc(skillById[id]) + '</span>'; }).join(' ')
            : '—';
        var html = '';
        html += card('Resume & Documents', 1, rows([
            ['Resume', fileName('resume')], ['Cover Letter', fileName('cover_letter_file')], ['Portfolio', pv('portfolio_url')]
        ]));
        html += card('Personal Information', 2, rows([
            ['Full Name', pv('full_name')], ['Email', pv('email')], ['Mobile', pv('mobile')],
            ['Location', pv('current_location')], ['LinkedIn', pv('linkedin_profile')]
        ]));
        html += card('Professional Information', 3, rows([
            ['Total Experience', pv('total_experience')], ['Relevant Experience', pv('relevant_experience')],
            ['Current Company', pv('current_company')], ['Designation', pv('current_designation')],
            ['Current CTC', pv('current_ctc')], ['Expected CTC', pv('expected_ctc')],
            ['Currency', pv('ctc_currency')], ['Notice Period', pv('notice_period')],
            ['Employment Status', pv('employment_status')]
        ]));
        html += card('Education', 4, rows([
            ['Qualification', pv('qualification')], ['Specialization', pv('specialization')],
            ['University / College', pv('university_college')], ['Graduation Year', pv('graduation_year')]
        ]));
        html += card('Skills', 5, rows([['Skills', skillNames, true]]));
        html += card('Additional Questions', 6, rows([
            ['Why interested', pv('why_interested')], ['Why CPVIA', pv('why_cpvia')], ['Willing to relocate', relocate]
        ]));
        review.innerHTML = html;
        review.querySelectorAll('.apply-review-edit').forEach(function (b) {
            b.addEventListener('click', function () { show(+b.getAttribute('data-goto')); });
        });
    }

    /* ---------------------------------------------------- localStorage draft */
    var TEXT_FIELDS = ['full_name', 'email', 'mobile', 'current_location', 'linkedin_profile',
        'total_experience', 'relevant_experience', 'current_company', 'current_designation',
        'current_ctc', 'expected_ctc', 'ctc_currency', 'notice_period', 'employment_status',
        'qualification', 'specialization', 'university_college', 'graduation_year',
        'portfolio_url', 'why_interested', 'why_cpvia'];

    function saveDraft() {
        try {
            var data = { step: current, skills: selectedSkills, relocate: relocateInput.value, fields: {} };
            TEXT_FIELDS.forEach(function (f) { var e = el(f); if (e) { data.fields[f] = e.value; } });
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) { /* ignore quota / privacy mode */ }
    }
    function restoreDraft() {
        var raw;
        try { raw = localStorage.getItem(STORAGE_KEY); } catch (e) { return false; }
        if (!raw) { return false; }
        var data;
        try { data = JSON.parse(raw); } catch (e) { return false; }
        if (!data || !data.fields) { return false; }
        TEXT_FIELDS.forEach(function (f) { var e = el(f); if (e && typeof data.fields[f] === 'string') { e.value = data.fields[f]; } });
        selectedSkills = (data.skills || []).filter(function (id) { return skillById[id]; });
        relocateInput.value = (data.relocate === '0' || data.relocate === '1') ? data.relocate : '';
        return true;
    }
    function clearDraft() { try { localStorage.removeItem(STORAGE_KEY); } catch (e) {} }

    var clearBtn = el('applyClearBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            clearDraft();
            window.location.href = 'apply.php?job_id=' + (cfg.jobId || 0);
        });
    }

    // Auto-save on any field change.
    form.addEventListener('input', function () { saveDraft(); });
    form.addEventListener('change', function () { saveDraft(); });

    /* ------------------------------------------------------------- submit */
    var submitting = false;
    form.addEventListener('submit', function (e) {
        // Validate every step; jump to the first invalid one.
        for (var s = 1; s <= TOTAL; s++) {
            if (!validateStep(s)) { e.preventDefault(); show(s); return; }
        }
        if (submitting) { e.preventDefault(); return; }
        submitting = true;
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Submitting…';
        // draft cleared on the success page.
    });

    // Progress bar step click (only to completed/next).
    stepItems.forEach(function (li) {
        li.addEventListener('click', function () {
            var s = +li.getAttribute('data-step');
            if (s < current) { show(s); }
            else if (s === current + 1) { next(); }
        });
    });

    btnNext.addEventListener('click', next);
    btnPrev.addEventListener('click', prev);
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'file') {
            e.preventDefault();
            if (current < TOTAL) { next(); }
        }
    });

    /* --------------------------------------------------------------- init */
    var serverError = (cfg.errorFields && cfg.errorFields.length > 0);
    if (serverError) {
        // Server re-render already populated fields + inline errors.
        // Re-read skills/relocate from the hidden inputs the server echoed.
        var pre = (skillsHidden.value || '').split(',').filter(Boolean).map(Number).filter(function (id) { return skillById[id]; });
        selectedSkills = pre;
        commitSkills();
        syncRelocateRadios();
        show(cfg.errorStep || 1);
    } else {
        var restored = restoreDraft();
        commitSkills();
        syncRelocateRadios();
        if (restored) {
            var banner = el('applyRestore');
            if (banner) { banner.hidden = false; }
            show(1);
        } else {
            show(1);
        }
    }
    renderDrop('');
}());
