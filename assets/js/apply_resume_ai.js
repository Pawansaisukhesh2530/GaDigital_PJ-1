/* =========================================================================
   CPVIA Resume Auto-Fill (optional enhancement for the Apply wizard)
   -------------------------------------------------------------------------
   Flow: candidate uploads resume (Step 1) -> "Analyze Resume" -> same-origin
   resume_parse.php -> FastAPI Resume Intelligence -> structured JSON ->
   centralized mapResumeToApplication() -> safe fill (empty fields only) +
   conservative skill matching. Manual application always keeps working.

   Depends on window.CPVIAApplyWizard (exposed by apply_wizard.js). If that API
   is unavailable, this module disables itself and the manual flow is unaffected.
   ========================================================================= */
(function () {
    'use strict';

    var W = window.CPVIAApplyWizard;
    var form = document.getElementById('applyForm');
    var btn = document.getElementById('aiAnalyzeBtn');
    var resumeInput = document.getElementById('resume');
    var statusBox = document.getElementById('aiStatus');
    if (!form || !btn || !resumeInput || !statusBox || !W) { return; } // manual flow only

    var csrfEl = form.querySelector('input[name="csrf_token"]');
    var jobIdEl = form.querySelector('input[name="job_id"]');
    var CSRF = csrfEl ? csrfEl.value : '';
    var JOB_ID = jobIdEl ? jobIdEl.value : '0';

    var PROCESSING_MESSAGES = [
        'Uploading your resume\u2026',
        'Reading your resume\u2026',
        'Extracting your information\u2026',
        'Preparing your application\u2026'
    ];
    // Timeout hierarchy (must stay ordered so the innermost layer returns a
    // controlled error first): cURL budget 300s < PHP allowance ~320s <
    // browser abort ~330s. The browser must never cancel while PHP is still
    // legitimately processing. Overridable via window.CPVIA_APPLY.aiTimeoutMs.
    var CLIENT_TIMEOUT_MS = (window.CPVIA_APPLY && +window.CPVIA_APPLY.aiTimeoutMs) || 330000;
    var analyzing = false;
    var msgTimer = null;

    /* ------------------------------------------------------------- utilities */
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function normSkill(s) { return String(s == null ? '' : s).trim().toLowerCase().replace(/\s+/g, ' '); }
    // Ensure a URL has a scheme (candidate never has to type "https://").
    function normUrl(v) {
        v = String(v == null ? '' : v).trim();
        if (!v) { return ''; }
        return /^[a-z][a-z0-9+.\-]*:\/\//i.test(v) ? v : 'https://' + v.replace(/^\/+/, '');
    }
    function setBusy(state) {
        analyzing = state;
        btn.disabled = state;
        btn.classList.toggle('is-busy', state);
    }

    /* -------------------------------------------------- processing UI states */
    function showProcessing() {
        var i = 0;
        statusBox.className = 'apply-ai-status is-processing';
        statusBox.innerHTML =
            '<div class="apply-ai-spinner" aria-hidden="true"></div>' +
            '<div class="apply-ai-bar"><span></span></div>' +
            '<p class="apply-ai-msg" id="aiMsg"></p>';
        var msgEl = document.getElementById('aiMsg');
        msgEl.textContent = PROCESSING_MESSAGES[0];
        msgTimer = setInterval(function () {
            i = Math.min(i + 1, PROCESSING_MESSAGES.length - 1);
            msgEl.textContent = PROCESSING_MESSAGES[i];
        }, 4000);
    }
    function stopProcessing() {
        if (msgTimer) { clearInterval(msgTimer); msgTimer = null; }
    }
    function showInfo(text) {
        statusBox.className = 'apply-ai-status is-info';
        statusBox.textContent = text;
    }
    function showError(text) {
        statusBox.className = 'apply-ai-status is-error';
        statusBox.textContent = text;
    }

    /* ------------------------------------------------ degree / skill helpers */
    function degreeRank(deg) {
        var d = normSkill(deg);
        if (/ph\.?\s?d|doctor/.test(d)) { return 4; }
        if (/master|m\.?tech|m\.?sc|mba|m\.?c\.?a|\bm\.?a\b|m\.?com|\bm\.?s\b|post\s?grad/.test(d)) { return 3; }
        if (/bachelor|b\.?tech|b\.?sc|b\.?c\.?a|\bb\.?a\b|\bb\.?e\b|b\.?com|\bb\.?s\b/.test(d)) { return 2; }
        if (/diploma/.test(d)) { return 1; }
        return 0;
    }
    function rankToQualification(rank) {
        return { 4: 'PhD', 3: "Master's", 2: "Bachelor's", 1: 'Diploma' }[rank] || null;
    }
    function extractYear(dateStr) {
        var m = String(dateStr == null ? '' : dateStr).match(/(19|20)\d{2}/);
        if (!m) { return null; }
        var y = parseInt(m[0], 10);
        var maxY = new Date().getFullYear() + 6;
        return (y >= 1950 && y <= maxY) ? String(y) : null;
    }
    function pickHighestEducation(list) {
        if (!Array.isArray(list) || !list.length) { return null; }
        var best = null, bestRank = -1, bestYear = -1;
        list.forEach(function (e) {
            if (!e || typeof e !== 'object') { return; }
            var rank = degreeRank(e.degree);
            var year = parseInt(extractYear(e.end_date) || '0', 10);
            if (rank > bestRank || (rank === bestRank && year > bestYear)) {
                best = e; bestRank = rank; bestYear = year;
            }
        });
        return best ? { entry: best, rank: bestRank } : null;
    }

    // Curated, conservative aliases ONLY (no fuzzy matching).
    var SKILL_ALIASES = {
        'r': 'r programming',
        'r language': 'r programming',
        'ml': 'machine learning',
        'artificial intelligence': 'ai',
        'structured query language': 'sql'
    };
    function flattenSkills(skillsObj) {
        var out = [], seen = {};
        if (!skillsObj || typeof skillsObj !== 'object') { return out; }
        Object.keys(skillsObj).forEach(function (cat) {
            var arr = skillsObj[cat];
            if (!Array.isArray(arr)) { return; }
            arr.forEach(function (s) {
                var n = normSkill(s);
                if (n && !seen[n]) { seen[n] = 1; out.push(String(s).trim()); }
            });
        });
        return out.slice(0, 60);
    }
    function matchSkillId(name, master) {
        var n = normSkill(name);
        if (!n) { return null; }
        for (var i = 0; i < master.length; i++) {
            if (normSkill(master[i].name) === n) { return master[i].id; }
        }
        if (SKILL_ALIASES[n]) {
            var alias = SKILL_ALIASES[n];
            for (var j = 0; j < master.length; j++) {
                if (normSkill(master[j].name) === alias) { return master[j].id; }
            }
        }
        return null;
    }

    /* ---------------------------------------------- centralized mapping layer */
    function mapResumeToApplication(structured) {
        var out = { fields: {}, selects: {}, skillNames: [] };
        if (!structured || typeof structured !== 'object') { return out; }

        var p = structured.personal || {};
        if (p.name) { out.fields.full_name = p.name; }
        if (p.email) { out.fields.email = p.email; }
        if (p.phone) { out.fields.mobile = p.phone; }
        if (p.location) { out.fields.current_location = p.location; }
        if (p.linkedin) { out.fields.linkedin_profile = normUrl(p.linkedin); }
        if (p.portfolio) { out.fields.portfolio_url = normUrl(p.portfolio); }

        // Professional — latest experience only; never guess CTC/notice/status.
        var exp = Array.isArray(structured.experience) ? structured.experience : [];
        if (exp.length && exp[0] && typeof exp[0] === 'object') {
            if (exp[0].company) { out.fields.current_company = exp[0].company; }
            if (exp[0].job_title) { out.fields.current_designation = exp[0].job_title; }
        }

        // Education — highest/latest qualification.
        var chosen = pickHighestEducation(structured.education);
        if (chosen) {
            var e = chosen.entry;
            if (e.field_of_study) { out.fields.specialization = e.field_of_study; }
            if (e.institution) { out.fields.university_college = e.institution; }
            var gy = extractYear(e.end_date) || extractYear(e.start_date);
            if (gy) { out.fields.graduation_year = gy; }
            var q = rankToQualification(chosen.rank);
            if (q) { out.selects.qualification = q; }
        }

        out.skillNames = flattenSkills(structured.skills);
        return out;
    }

    /* -------------------------------------------------------- apply mapping */
    function markFilled(id) {
        var elField = document.getElementById(id);
        if (!elField) { return; }
        var wrap = elField.closest('.apply-field');
        if (!wrap || wrap.querySelector('.apply-ai-filled')) { return; }
        var tag = document.createElement('span');
        tag.className = 'apply-ai-filled';
        tag.textContent = 'Filled from resume';
        wrap.appendChild(tag);
    }
    function applyMapping(mapped) {
        var filled = [], retained = 0;
        Object.keys(mapped.fields).forEach(function (id) {
            if (!W.fieldExists(id)) { return; }
            var wasEmpty = W.isFieldEmpty(id);
            if (W.setFieldIfEmpty(id, mapped.fields[id])) { filled.push(id); markFilled(id); }
            else if (!wasEmpty) { retained++; }
        });
        Object.keys(mapped.selects).forEach(function (id) {
            if (W.setFieldIfEmpty(id, mapped.selects[id])) { filled.push(id); markFilled(id); }
        });

        // Skills: match against the recruitment master list; never create records.
        var master = W.listSkills();
        var matched = [], matchedIds = [], unmatched = [];
        mapped.skillNames.forEach(function (name) {
            var id = matchSkillId(name, master);
            if (id != null) {
                var alreadySelected = W.isSkillSelected(id);
                if (W.selectSkillById(id)) {
                    matched.push(name);
                    // Only track skills WE added, so Clear won't remove pre-existing ones.
                    if (!alreadySelected) { matchedIds.push(id); }
                }
            } else { unmatched.push(name); }
        });

        return { filled: filled, retained: retained, matched: matched, matchedIds: matchedIds, unmatched: unmatched };
    }

    /* ---------------------------------------------------- success rendering */
    function renderSuccess(summary) {
        stopProcessing();
        statusBox.className = 'apply-ai-status is-success';
        var html = '<div class="apply-ai-success-head">'
            + '<strong>Resume analyzed successfully.</strong>'
            + '<span>We\u2019ve pre-filled the information we found. Please review and correct any details before submitting.</span>'
            + '</div>';
        if (summary.retained > 0) {
            html += '<p class="apply-ai-note-sm">Some fields you already filled were kept as-is.</p>';
        }
        if (summary.unmatched.length) {
            var chips = summary.unmatched.slice(0, 15).map(function (s) {
                return '<span class="apply-ai-xchip">' + esc(s) + '</span>';
            }).join(' ');
            html += '<div class="apply-ai-extra"><span class="apply-ai-extra-label">Also found in your resume '
                + '(not in our skills list \u2014 add manually if relevant):</span><div class="apply-ai-xchips">'
                + chips + '</div></div>';
        }
        html += '<div class="apply-ai-actions">'
            + '<button type="button" class="apply-btn apply-btn-primary apply-ai-continue" id="aiContinueBtn">'
            + 'Continue to Personal Information</button>'
            + '<button type="button" class="apply-btn apply-btn-ghost apply-ai-clear" id="aiClearBtn">'
            + 'Clear auto-filled data</button>'
            + '</div>';
        statusBox.innerHTML = html;
        var cont = document.getElementById('aiContinueBtn');
        if (cont) { cont.addEventListener('click', function () { W.goToStep(2); }); }
        var clr = document.getElementById('aiClearBtn');
        if (clr) { clr.addEventListener('click', function () { clearAutofill(summary); }); }
    }

    // Remove only what the AI filled: the auto-filled fields, the skill chips we
    // added, and the "Filled from resume" badges. Candidate-entered data is left
    // untouched (we only tracked fields/skills that were empty before analysis).
    function clearAutofill(summary) {
        (summary.filled || []).forEach(function (id) {
            W.clearField(id);
            removeFilledBadge(id);
        });
        (summary.matchedIds || []).forEach(function (id) { W.deselectSkillById(id); });
        showInfo('Auto-filled data cleared. You can analyze again or fill the form manually.');
    }

    function removeFilledBadge(id) {
        var elField = document.getElementById(id);
        if (!elField) { return; }
        var wrap = elField.closest('.apply-field');
        var tag = wrap && wrap.querySelector('.apply-ai-filled');
        if (tag) { tag.parentNode.removeChild(tag); }
    }

    /* --------------------------------------------------------- the request */
    function analyze() {
        if (analyzing) { return; }
        if (!resumeInput.files || !resumeInput.files.length) {
            showError('Please attach your resume above before analyzing.');
            return;
        }
        setBusy(true);
        showProcessing();

        var fd = new FormData();
        fd.append('resume', resumeInput.files[0]);
        fd.append('csrf_token', CSRF);
        fd.append('job_id', JOB_ID);

        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timeoutId = controller ? setTimeout(function () { controller.abort(); }, CLIENT_TIMEOUT_MS) : null;

        fetch('resume_parse.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        }).then(function (resp) {
            return resp.json().then(function (json) { return { ok: resp.ok, json: json }; })
                .catch(function () { return { ok: false, json: null }; });
        }).then(function (res) {
            if (timeoutId) { clearTimeout(timeoutId); }
            stopProcessing();
            setBusy(false);
            if (res.json && res.json.success && res.json.data) {
                var mapped = mapResumeToApplication(res.json.data);
                var summary = applyMapping(mapped);
                renderSuccess(summary);
            } else {
                var msg = (res.json && res.json.message)
                    ? res.json.message
                    : 'We could not analyze your resume automatically. You can continue filling out your application manually.';
                showError(msg);
            }
        }).catch(function (err) {
            if (timeoutId) { clearTimeout(timeoutId); }
            stopProcessing();
            setBusy(false);
            if (err && err.name === 'AbortError') {
                showError('Resume analysis took too long. You can continue filling out your application manually.');
            } else {
                showError('Automatic resume reading is currently unavailable. You can continue filling out your application manually.');
            }
        });
    }

    btn.addEventListener('click', analyze);

    // Reset the status if the candidate picks a different resume.
    resumeInput.addEventListener('change', function () {
        if (!analyzing) { statusBox.className = 'apply-ai-status'; statusBox.textContent = ''; statusBox.innerHTML = ''; }
    });
}());
