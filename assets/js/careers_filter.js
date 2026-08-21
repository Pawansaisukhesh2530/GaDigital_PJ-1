/**
 * careers_filter.js
 * ---------------------------------------------------------------------------
 * Client-side filtering for the CPVIA careers page.
 *
 * Logic:
 *  - OR within a category  (card matches if it has ANY selected value in that category)
 *  - AND between categories (card must pass every active category)
 *
 * No libraries. No page reload. Pure DOM show/hide.
 * ---------------------------------------------------------------------------
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // --- DOM references ---
        var pills       = document.querySelectorAll('.filter-pill');
        var cards       = document.querySelectorAll('.job-card');
        var clearBtn    = document.getElementById('filter-clear-btn');
        var noResults   = document.getElementById('filter-no-results');
        var noResultsClr = document.getElementById('filter-no-results-clear');
        var jobList     = document.querySelector('.job-list');

        // Bail out gracefully if the filter bar or job list doesn't exist on the page
        if (!pills.length || !cards.length) {
            return;
        }

        // --- Filter state ---
        var state = {
            location: [],
            employment_type: [],
            skills: []
        };

        // --- Helpers ---

        /**
         * Check whether the filter state has any active selections.
         */
        function hasActiveFilters() {
            return state.location.length > 0 ||
                   state.employment_type.length > 0 ||
                   state.skills.length > 0;
        }

        /**
         * Determine whether a single job card should be visible given the current state.
         */
        function cardMatchesFilters(card) {
            // Location check: OR within category
            if (state.location.length > 0) {
                var cardLocation = (card.getAttribute('data-location') || '').trim();
                if (state.location.indexOf(cardLocation) === -1) {
                    return false;
                }
            }

            // Employment type check: OR within category
            if (state.employment_type.length > 0) {
                var cardType = (card.getAttribute('data-type') || '').trim();
                if (state.employment_type.indexOf(cardType) === -1) {
                    return false;
                }
            }

            // Skills check: OR within category (comma-separated on card)
            if (state.skills.length > 0) {
                var rawSkills = (card.getAttribute('data-skills') || '').trim();
                if (!rawSkills) {
                    return false; // card has no skills listed — cannot match
                }
                var cardSkills = rawSkills.split(',').map(function (s) {
                    return s.trim().toLowerCase();
                });
                var matchesAnySkill = state.skills.some(function (selected) {
                    return cardSkills.indexOf(selected.toLowerCase()) !== -1;
                });
                if (!matchesAnySkill) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Run the filter: show/hide cards, toggle no-results, toggle clear button.
         */
        function applyFilters() {
            var visibleCount = 0;

            if (!hasActiveFilters()) {
                // No filters active — show everything
                cards.forEach(function (card) {
                    card.style.display = '';
                });
                visibleCount = cards.length;
            } else {
                cards.forEach(function (card) {
                    if (cardMatchesFilters(card)) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
            }

            // Toggle no-results message and job list visibility
            if (visibleCount === 0) {
                if (noResults) noResults.style.display = '';
                if (jobList)   jobList.style.display = 'none';
            } else {
                if (noResults) noResults.style.display = 'none';
                if (jobList)   jobList.style.display = '';
            }

            // Toggle clear button visibility
            if (clearBtn) {
                clearBtn.style.display = hasActiveFilters() ? '' : 'none';
            }
        }

        /**
         * Reset all filters to their default (empty) state.
         */
        function clearAllFilters() {
            state.location = [];
            state.employment_type = [];
            state.skills = [];

            // Remove active class from all pills
            pills.forEach(function (pill) {
                pill.classList.remove('filter-pill--active');
            });

            applyFilters();
        }

        // --- Event listeners ---

        // Pill click: toggle selection
        pills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                var category = pill.getAttribute('data-category');
                var value    = pill.getAttribute('data-value');

                if (!category || !value || !state.hasOwnProperty(category)) {
                    return;
                }

                var idx = state[category].indexOf(value);

                if (idx === -1) {
                    // Add selection
                    state[category].push(value);
                    pill.classList.add('filter-pill--active');
                } else {
                    // Remove selection
                    state[category].splice(idx, 1);
                    pill.classList.remove('filter-pill--active');
                }

                applyFilters();
            });
        });

        // Clear buttons
        if (clearBtn) {
            clearBtn.addEventListener('click', clearAllFilters);
        }
        if (noResultsClr) {
            noResultsClr.addEventListener('click', clearAllFilters);
        }
    });
})();
