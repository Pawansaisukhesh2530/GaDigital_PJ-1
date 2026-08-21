/* =========================================================================
   CPVIA Single-Page Job Editor — Client-side behavior
   Handles:
     1. URL-based section targeting (?section=N)
     2. Section expand/collapse (native <details> + optional accordion)
     3. Unsaved changes detection with beforeunload prompt
   ========================================================================= */
(function () {
    'use strict';

    /* ----------------------------------------------------------
       1. URL-based section targeting
       ---------------------------------------------------------- */
    var params = new URLSearchParams(window.location.search);
    var targetSection = params.get('section');

    if (targetSection) {
        var allDetails = document.querySelectorAll('details[data-section]');
        var target = document.querySelector('details[data-section="' + targetSection + '"]');

        if (target) {
            // Close all sections first
            allDetails.forEach(function (el) {
                el.removeAttribute('open');
            });
            // Open the targeted section
            target.setAttribute('open', '');
            // Scroll into view after a brief delay to let the browser paint
            setTimeout(function () {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }

    /* ----------------------------------------------------------
       2. Accordion behavior (optional enhancement)
       When a section is opened, close the others for focus.
       ---------------------------------------------------------- */
    var sections = document.querySelectorAll('details[data-section]');

    sections.forEach(function (details) {
        var summary = details.querySelector('summary');
        if (!summary) { return; }

        summary.addEventListener('click', function () {
            // The toggle hasn't happened yet at this point, so if the
            // section is currently closed it's about to open.
            if (!details.hasAttribute('open')) {
                // Close other sections
                sections.forEach(function (other) {
                    if (other !== details) {
                        other.removeAttribute('open');
                    }
                });
            }
        });
    });

    /* ----------------------------------------------------------
       3. Unsaved changes detection
       ---------------------------------------------------------- */
    // Set of form IDs that have been modified since last save
    var dirtyForms = new Set();

    /**
     * Mark a form as dirty when any of its fields change.
     */
    function markDirty(event) {
        var form = event.target.closest('form');
        var key = form ? (form.id || form.getAttribute('data-section') || 'unknown') : null;
        if (key) {
            dirtyForms.add(key);
        }
    }

    // Listen for input/change events on all forms within the editor
    var editorContainer = document.querySelector('.single-editor') ||
                          document.querySelector('.edit-single-page') ||
                          document.body;

    editorContainer.addEventListener('input', markDirty);
    editorContainer.addEventListener('change', markDirty);

    /**
     * When a form is submitted, remove it from the dirty set so the
     * beforeunload prompt doesn't fire for that save operation.
     */
    var forms = editorContainer.querySelectorAll('form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            var key = form.id || form.getAttribute('data-section') || 'unknown';
            dirtyForms.delete(key);
        });
    });

    /**
     * Warn the user if they try to navigate away with unsaved changes.
     */
    window.addEventListener('beforeunload', function (e) {
        if (dirtyForms.size > 0) {
            // Standard approach — most browsers show their own message
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });

})();
