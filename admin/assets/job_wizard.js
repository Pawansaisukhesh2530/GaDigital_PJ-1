/* =========================================================================
   CPVIA Job Posting Wizard
   Self-contained: step navigation, inline validation, skill chips,
   lightweight rich-text editor, and review rendering.
   Reusable by Add Job (and Edit Job later) — reads initial state from
   window.CPVIA_SKILLS / CPVIA_REQUIRED / CPVIA_PREFERRED.
   ========================================================================= */
(function () {
    'use strict';

    var TOTAL = 8;
    var STEP_NAMES = ['Basic Job Details', 'Location', 'Experience & Education',
        'Salary & Skills', 'Job Description', 'Responsibilities & Requirements',
        'Benefits & Candidate Preferences', 'Review & Publish'];

    var wizard = document.getElementById('jobWizard');
    if (!wizard) { return; }

    var form = document.getElementById('jobWizardForm');
    var panels = wizard.querySelectorAll('.wizard-panel');
    var stepItems = wizard.querySelectorAll('.wizard-step-item');
    var progressFill = document.getElementById('wizProgressFill');
    var stepNumEl = document.getElementById('wizStepNum');
    var stepNameEl = document.getElementById('wizStepName');

    var btnPrev = document.getElementById('wizPrev');
    var btnNext = document.getElementById('wizNext');
    var btnDraft = document.getElementById('wizSaveDraft');
    var btnPublish = document.getElementById('wizPublish');
    var actionInput = document.getElementById('wizardAction');

    var current = 1;
    var skills = window.CPVIA_SKILLS || [];
    var skillById = {};
    skills.forEach(function (s) { skillById[s.id] = s.name; });

    /* ---------------------------------------------------------------- utils */
    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function stripTags(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return (tmp.textContent || tmp.innerText || '').replace(/\u00a0/g, ' ').trim();
    }

    /* ---------------------------------------------------- rich text editors */
    var RTE_BUTTONS = [
        { cmd: 'bold', label: 'B', title: 'Bold', style: 'font-weight:800;' },
        { cmd: 'italic', label: 'I', title: 'Italic', style: 'font-style:italic;' },
        { cmd: 'underline', label: 'U', title: 'Underline', style: 'text-decoration:underline;' },
        { cmd: 'insertUnorderedList', label: '&bull; List', title: 'Bullet list' },
        { cmd: 'insertOrderedList', label: '1. List', title: 'Numbered list' },
        { cmd: 'createLink', label: 'Link', title: 'Insert link' },
        { cmd: 'removeFormat', label: 'Clear', title: 'Clear formatting' }
    ];

    function initEditor(rte) {
        var toolbar = rte.querySelector('.rte-toolbar');
        var area = rte.querySelector('.rte-area');
        var targetId = rte.getAttribute('data-rte-target');
        var hidden = document.getElementById(targetId);

        RTE_BUTTONS.forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rte-btn';
            btn.title = b.title;
            btn.setAttribute('aria-label', b.title);
            btn.innerHTML = b.label;
            if (b.style) { btn.setAttribute('style', b.style); }
            btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            btn.addEventListener('click', function () {
                area.focus();
                if (b.cmd === 'createLink') {
                    openLinkPopover(rte, area, hidden);
                    return;
                }
                document.execCommand(b.cmd, false, null);
                sync();
            });
            toolbar.appendChild(btn);
        });

        function sync() { if (hidden) { hidden.value = area.innerHTML.trim() === '<br>' ? '' : area.innerHTML; } }
        area.addEventListener('input', sync);
        area.addEventListener('blur', sync);
        rte._sync = sync;
        sync();
    }

    function openLinkPopover(rte, area, hidden) {
        var existing = rte.querySelector('.rte-link-pop');
        if (existing) { existing.remove(); return; }

        // Preserve selection
        var sel = window.getSelection();
        var range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
        var hasSelection = range && !range.collapsed;

        var pop = document.createElement('div');
        pop.className = 'rte-link-pop';
        pop.innerHTML = '<input type="url" class="rte-link-input" placeholder="https://example.com">' +
            '<button type="button" class="rte-link-apply">Apply</button>' +
            '<button type="button" class="rte-link-cancel">Cancel</button>' +
            '<div class="rte-link-msg"></div>';
        rte.querySelector('.rte-toolbar').appendChild(pop);
        var input = pop.querySelector('.rte-link-input');
        var msg = pop.querySelector('.rte-link-msg');
        input.focus();

        function close() { pop.remove(); }
        pop.querySelector('.rte-link-cancel').addEventListener('click', close);
        pop.querySelector('.rte-link-apply').addEventListener('click', function () {
            var url = input.value.trim();
            if (!/^https?:\/\/.+/i.test(url)) { msg.textContent = 'Enter a valid http(s) URL.'; return; }
            area.focus();
            if (range) { sel.removeAllRanges(); sel.addRange(range); }
            if (hasSelection) {
                document.execCommand('createLink', false, url);
            } else {
                document.execCommand('insertHTML', false,
                    '<a href="' + escapeHtml(url) + '">' + escapeHtml(url) + '</a>');
            }
            if (rte._sync) { rte._sync(); }
            close();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); pop.querySelector('.rte-link-apply').click(); }
            if (e.key === 'Escape') { close(); }
        });
    }

    function syncEditors() {
        wizard.querySelectorAll('.rte').forEach(function (rte) { if (rte._sync) { rte._sync(); } });
    }

    /* ------------------------------------------------------- skill pickers */
    // Registry so the two pickers can exclude each other's selections
    // (a skill cannot be both Required and Preferred).
    var skillPickers = [];
    function selectedAnywhere(id) {
        return skillPickers.some(function (p) { return p.get().indexOf(id) !== -1; });
    }

    function initSkillPicker(picker) {
        var targetId = picker.getAttribute('data-skill-target');
        var type = picker.getAttribute('data-skill-type');
        var hidden = document.getElementById(targetId);
        var search = picker.querySelector('.skill-search');
        var dropdown = picker.querySelector('.skill-dropdown');
        var chipWrap = picker.querySelector('.skill-chips');

        var initial = (type === 'required' ? (window.CPVIA_REQUIRED || []) : (window.CPVIA_PREFERRED || []));
        var selected = initial.map(Number).filter(function (id) { return skillById[id]; });
        skillPickers.push({ get: function () { return selected; } });

        function commit() {
            hidden.value = selected.join(',');
            renderChips();
        }
        function renderChips() {
            chipWrap.innerHTML = '';
            selected.forEach(function (id) {
                var chip = document.createElement('span');
                chip.className = 'skill-chip';
                chip.innerHTML = escapeHtml(skillById[id]) +
                    '<button type="button" class="skill-chip-x" aria-label="Remove ' + escapeHtml(skillById[id]) + '">&times;</button>';
                chip.querySelector('.skill-chip-x').addEventListener('click', function () {
                    selected = selected.filter(function (x) { return x !== id; });
                    commit();
                });
                chipWrap.appendChild(chip);
            });
        }
        function renderDropdown(q) {
            q = (q || '').toLowerCase().trim();
            dropdown.innerHTML = '';
            var matches = skills.filter(function (s) {
                return !selectedAnywhere(s.id) && (q === '' || s.name.toLowerCase().indexOf(q) !== -1);
            }).slice(0, 8);
            if (!matches.length) {
                dropdown.classList.remove('open');
                return;
            }
            matches.forEach(function (s, idx) {
                var opt = document.createElement('div');
                opt.className = 'skill-option' + (idx === 0 ? ' active' : '');
                opt.setAttribute('role', 'option');
                opt.textContent = s.name;
                opt.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    add(s.id);
                });
                dropdown.appendChild(opt);
            });
            dropdown.classList.add('open');
        }
        function add(id) {
            if (selected.indexOf(id) === -1) { selected.push(id); commit(); }
            search.value = '';
            renderDropdown('');
            search.focus();
        }

        search.addEventListener('input', function () { renderDropdown(search.value); });
        search.addEventListener('focus', function () { renderDropdown(search.value); });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = dropdown.querySelector('.skill-option.active') || dropdown.querySelector('.skill-option');
                if (first) { first.dispatchEvent(new MouseEvent('mousedown')); }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('open');
            }
        });
        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target)) { dropdown.classList.remove('open'); }
        });

        commit();
    }

    /* --------------------------------------------------------- validation */
    function clearErrors(panel) {
        panel.querySelectorAll('.field-error').forEach(function (el) { el.textContent = ''; });
        panel.querySelectorAll('.has-error').forEach(function (el) { el.classList.remove('has-error'); });
    }
    function setError(panel, field, message) {
        var msgEl = panel.querySelector('.field-error[data-error-for="' + field + '"]');
        if (msgEl) { msgEl.textContent = message; }
        var input = document.getElementById(field);
        if (input) { input.classList.add('has-error'); }
        var rte = panel.querySelector('.rte[data-rte-target="' + field + 'Input"]');
        if (rte) { rte.classList.add('has-error'); }
    }
    function numOk(v) { return v === '' || (!isNaN(parseFloat(v)) && isFinite(v) && parseFloat(v) >= 0); }

    function validateStep(n, forPublish) {
        var panel = wizard.querySelector('.wizard-panel[data-panel="' + n + '"]');
        clearErrors(panel);
        syncEditors();
        var ok = true;
        function fail(field, msg) { setError(panel, field, msg); ok = false; }

        if (n === 1) {
            if (!val('title')) { fail('title', 'Job title is required.'); }
            if (!val('department')) { fail('department', 'Department is required.'); }
            if (!val('employment_type')) { fail('employment_type', 'Select an employment type.'); }
            var op = val('number_of_openings');
            if (op !== '' && (parseInt(op, 10) < 1 || isNaN(parseInt(op, 10)))) { fail('number_of_openings', 'Must be at least 1.'); }
        } else if (n === 2) {
            if (!val('city')) { fail('city', 'City is required.'); }
        } else if (n === 3) {
            if (!numOk(val('min_experience'))) { fail('min_experience', 'Enter a valid number.'); }
            if (!numOk(val('max_experience'))) { fail('max_experience', 'Enter a valid number.'); }
            if (val('min_experience') && val('max_experience') &&
                parseFloat(val('min_experience')) > parseFloat(val('max_experience'))) {
                fail('max_experience', 'Max must be greater than or equal to min.');
            }
        } else if (n === 4) {
            if (!numOk(val('min_salary'))) { fail('min_salary', 'Enter a valid amount.'); }
            if (!numOk(val('max_salary'))) { fail('max_salary', 'Enter a valid amount.'); }
            if (val('min_salary') && val('max_salary') &&
                parseFloat(val('min_salary')) > parseFloat(val('max_salary'))) {
                fail('max_salary', 'Max must be greater than or equal to min.');
            }
        } else if (n === 5) {
            if (!stripTags(document.getElementById('descriptionInput').value)) { fail('description', 'A description is required to publish.'); }
        } else if (n === 6) {
            if (!stripTags(document.getElementById('requirementsInput').value)) { fail('requirements', 'Requirements are required to publish.'); }
        } else if (n === 7) {
            if (val('minimum_age') && (parseInt(val('minimum_age'), 10) < 16)) { fail('minimum_age', 'Minimum age looks too low.'); }
            if (val('minimum_age') && val('maximum_age') &&
                parseInt(val('minimum_age'), 10) > parseInt(val('maximum_age'), 10)) {
                fail('maximum_age', 'Max age must be greater than or equal to min age.');
            }
            // Application Delivery
            var mode = selectedMode();
            if (modeNeedsEmail(mode)) {
                var raw = val('recipient_emails');
                if (!raw) {
                    fail('recipient_emails', 'Add at least one recipient email for this delivery option.');
                } else {
                    var parsed = parseEmailList(raw);
                    if (!parsed.ok || !parsed.emails.length) {
                        fail('recipient_emails', 'Enter valid email addresses separated by commas.');
                    }
                }
            }
        }
        return ok;
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    /* --------------------------------------------------- application delivery */
    function selectedMode() {
        var checked = wizard.querySelector('input[name="submission_mode"]:checked');
        return checked ? checked.value : 'BACKEND_ONLY';
    }
    function modeNeedsEmail(mode) {
        return mode === 'EMAIL_ONLY' || mode === 'BACKEND_AND_EMAIL';
    }
    function modeLabel(mode) {
        if (mode === 'EMAIL_ONLY') { return 'Email Only'; }
        if (mode === 'BACKEND_AND_EMAIL') { return 'Backend Dashboard + Email'; }
        return 'Backend Dashboard Only';
    }
    function parseEmailList(raw) {
        // Mirror of the server-side validator (settings_helpers.php).
        if (/[\r\n\t\0]/.test(raw)) { return { ok: false, emails: [] }; }
        var parts = raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        var emails = [];
        for (var i = 0; i < parts.length; i++) {
            if (!re.test(parts[i])) { return { ok: false, emails: [] }; }
            emails.push(parts[i]);
        }
        return { ok: true, emails: emails };
    }
    function initDelivery() {
        var radios = wizard.querySelectorAll('input[name="submission_mode"]');
        var group = document.getElementById('recipientEmailsGroup');
        if (!radios.length || !group) { return; }
        function refresh() {
            group.style.display = modeNeedsEmail(selectedMode()) ? '' : 'none';
        }
        radios.forEach(function (r) { r.addEventListener('change', refresh); });
        refresh();
    }

    /* --------------------------------------------------------- navigation */
    function showStep(n) {
        current = Math.max(1, Math.min(TOTAL, n));
        panels.forEach(function (p) {
            p.classList.toggle('is-active', parseInt(p.getAttribute('data-panel'), 10) === current);
        });
        stepItems.forEach(function (li) {
            var s = parseInt(li.getAttribute('data-step'), 10);
            li.classList.toggle('is-active', s === current);
            li.classList.toggle('is-complete', s < current);
        });
        progressFill.style.width = ((current - 1) / (TOTAL - 1) * 100) + '%';
        stepNumEl.textContent = current;
        stepNameEl.textContent = STEP_NAMES[current - 1];

        btnPrev.style.visibility = current === 1 ? 'hidden' : 'visible';
        btnNext.style.display = current === TOTAL ? 'none' : '';
        btnPublish.style.display = current === TOTAL ? '' : 'none';

        if (current === TOTAL) { buildReview(); }
        wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function goNext() {
        if (validateStep(current)) { showStep(current + 1); }
    }
    function goPrev() { showStep(current - 1); }

    /* ------------------------------------------------------------- review */
    function rowsHtml(rows) {
        return rows.map(function (r) {
            return '<div class="review-row"><span class="review-k">' + escapeHtml(r[0]) +
                '</span><span class="review-v">' + (r[2] ? r[1] : escapeHtml(r[1] || '—')) + '</span></div>';
        }).join('');
    }
    function skillNames(inputId) {
        var ids = (document.getElementById(inputId).value || '').split(',').filter(Boolean);
        if (!ids.length) { return '—'; }
        return ids.map(function (id) { return '<span class="review-chip">' + escapeHtml(skillById[id] || '') + '</span>'; }).join(' ');
    }
    function selText(id) {
        var el = document.getElementById(id);
        if (!el) { return ''; }
        if (el.tagName === 'SELECT') { return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : ''; }
        return el.value.trim();
    }
    function richOrDash(inputId) {
        var v = document.getElementById(inputId).value;
        return stripTags(v) ? '<div class="review-rte">' + v + '</div>' : '—';
    }

    function buildReview() {
        syncEditors();
        var grid = document.getElementById('reviewGrid');
        var remote = document.getElementById('remote_available').checked ? 'Yes' : 'No';

        function card(title, step, bodyHtml, full) {
            return '<div class="review-card' + (full ? ' review-card--full' : '') + '"><div class="review-card-head"><h4>' + escapeHtml(title) +
                '</h4><button type="button" class="review-edit" data-goto="' + step + '">Edit</button></div>' +
                bodyHtml + '</div>';
        }

        var html = '';
        html += card('Basic Details', 1, rowsHtml([
            ['Job Title', val('title')], ['Department', val('department')], ['Job Code', val('job_code')],
            ['Employment Type', selText('employment_type')], ['Work Mode', selText('work_mode')],
            ['Openings', val('number_of_openings')], ['Priority', selText('hiring_priority')]
        ]));
        html += card('Location', 2, rowsHtml([
            ['Country', val('country')], ['State', val('state')], ['City', val('city')],
            ['Office', val('office_location')], ['Remote Available', remote]
        ]));
        html += card('Experience & Education', 3, rowsHtml([
            ['Min Experience', val('min_experience')], ['Max Experience', val('max_experience')],
            ['Min Qualification', selText('minimum_qualification')], ['Degree', val('degree')],
            ['Specialization', val('specialization')]
        ]));
        html += card('Salary', 4, rowsHtml([
            ['Salary Type', selText('salary_type')], ['Currency', selText('currency')],
            ['Min Salary', val('min_salary')], ['Max Salary', val('max_salary')]
        ]));
        html += card('Skills', 4, rowsHtml([
            ['Required', skillNames('requiredSkillsInput'), true],
            ['Preferred', skillNames('preferredSkillsInput'), true]
        ]));
        html += card('Description', 5, richOrDash('descriptionInput'), true);
        html += card('Responsibilities', 6, richOrDash('responsibilitiesInput'), true);
        html += card('Requirements', 6, richOrDash('requirementsInput'), true);
        html += card('Benefits', 7, richOrDash('benefitsInput'), true);
        html += card('Candidate Preferences', 7, rowsHtml([
            ['Notice Period', val('preferred_notice_period')], ['Gender Preference', selText('gender_preference')],
            ['Min Age', val('minimum_age')], ['Max Age', val('maximum_age')]
        ]));
        var dMode = selectedMode();
        var deliveryRows = [['Receive Via', modeLabel(dMode)]];
        if (modeNeedsEmail(dMode)) { deliveryRows.push(['Recipient Email(s)', val('recipient_emails') || '—']); }
        html += card('Application Delivery', 7, rowsHtml(deliveryRows));

        grid.innerHTML = html;
        grid.querySelectorAll('.review-edit').forEach(function (b) {
            b.addEventListener('click', function () { showStep(parseInt(b.getAttribute('data-goto'), 10)); });
        });
    }

    /* --------------------------------------------------------- submitting */
    function submitWith(action) {
        syncEditors();
        if (action === 'publish') {
            for (var s = 1; s <= 7; s++) {
                if (!validateStep(s, true)) { showStep(s); return; }
            }
        } else {
            // Draft: only a title is required.
            if (!val('title')) { showStep(1); validateStep(1); return; }
        }
        actionInput.value = action;
        btnDraft.disabled = btnPublish.disabled = btnNext.disabled = true;
        form.submit();
    }

    /* -------------------------------------------------------------- wire up */
    wizard.querySelectorAll('.rte').forEach(initEditor);
    wizard.querySelectorAll('.skill-picker').forEach(initSkillPicker);
    initDelivery();

    btnNext.addEventListener('click', goNext);
    btnPrev.addEventListener('click', goPrev);
    btnDraft.addEventListener('click', function () { submitWith('draft'); });
    btnPublish.addEventListener('click', function () { submitWith('publish'); });

    // Clicking a completed/earlier step in the progress bar jumps to it.
    stepItems.forEach(function (li) {
        li.addEventListener('click', function () {
            var s = parseInt(li.getAttribute('data-step'), 10);
            if (s < current) { showStep(s); }
            else if (s === current + 1) { goNext(); }
        });
    });

    // Enter key inside a text field advances instead of submitting the form.
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'button') {
            e.preventDefault();
            if (current < TOTAL) { goNext(); }
        }
    });

    showStep(1);
}());
