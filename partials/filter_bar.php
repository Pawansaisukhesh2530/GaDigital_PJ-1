<?php
/**
 * partials/filter_bar.php
 * -----------------------------------------------------------------------------
 * Renders the filter bar UI with filter pills grouped by category.
 * Also embeds job data as JSON for client-side filtering.
 *
 * Expected variables (set by the including page):
 *   $filter_options  array  Keys: 'location', 'employment_type', 'skills' (arrays of strings)
 *   $jobs            array  Full job data (array of associative arrays)
 * -----------------------------------------------------------------------------
 */

// Guard: ensure required variables are available
if (!isset($filter_options) || !is_array($filter_options)) {
    $filter_options = ['location' => [], 'employment_type' => [], 'skills' => []];
}
if (!isset($jobs) || !is_array($jobs)) {
    $jobs = [];
}

$has_any_filters = !empty($filter_options['location'])
    || !empty($filter_options['employment_type'])
    || !empty($filter_options['skills']);
?>

<?php if ($has_any_filters): ?>
<div class="filter-bar" id="careers-filter-bar">

    <?php if (!empty($filter_options['location'])): ?>
        <span class="filter-bar__label">Location:</span>
        <?php foreach ($filter_options['location'] as $location): ?>
            <button type="button"
                    class="filter-pill"
                    data-category="location"
                    data-value="<?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>
            </button>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($filter_options['employment_type'])): ?>
        <span class="filter-bar__label">Type:</span>
        <?php foreach ($filter_options['employment_type'] as $type): ?>
            <button type="button"
                    class="filter-pill"
                    data-category="employment_type"
                    data-value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>
            </button>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($filter_options['skills'])): ?>
        <span class="filter-bar__label">Skills:</span>
        <?php foreach ($filter_options['skills'] as $skill): ?>
            <button type="button"
                    class="filter-pill"
                    data-category="skills"
                    data-value="<?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?>
            </button>
        <?php endforeach; ?>
    <?php endif; ?>

    <button type="button" class="filter-bar__clear" id="filter-clear-btn" style="display:none;">
        Clear filters
    </button>

</div>
<?php endif; ?>

<!-- No results message (hidden by default, shown by JS when filters yield zero matches) -->
<div class="filter-no-results" id="filter-no-results" style="display:none;">
    <p>No matching positions found for the selected filters.</p>
    <button type="button" class="filter-bar__clear" id="filter-no-results-clear">
        Clear all filters
    </button>
</div>

<!-- Job data embedded as JSON for client-side filtering -->
<script type="application/json" id="careers-job-data">
<?php echo json_encode($jobs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
</script>
