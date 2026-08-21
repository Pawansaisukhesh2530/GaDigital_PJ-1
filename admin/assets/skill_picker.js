/* =========================================================================
   CPVIA Standalone Skill Picker
   Extracted from job_wizard.js for use in pages that don't use the
   wizard step navigation (e.g. the single-page editor).
   
   Expects:
     window.cpviaSkillsData    - Array of { id, name } objects
     window.cpviaRequiredSelected - Array of preselected required skill IDs
     window.cpviaPreferredSelected - Array of preselected preferred skill IDs
   
   Initializes any .skill-picker elements using data-skill-target and
   data-skill-type attributes.
   ========================================================================= */
(function () {
    'use strict';

    var skills = window.cpviaSkillsData || [];
    var skillById = {};
    skills.forEach(function (s) { skillById[s.id] = s.name; });

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Registry so the two pickers can exclude each other's selections
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

        if (!hidden || !search || !dropdown || !chipWrap) { return; }

        var initial = (type === 'required'
            ? (window.cpviaRequiredSelected || [])
            : (window.cpviaPreferredSelected || []));
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

    // Initialize all skill pickers on the page
    document.querySelectorAll('.skill-picker').forEach(initSkillPicker);

}());
