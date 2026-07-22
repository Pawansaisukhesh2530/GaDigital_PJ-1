"""
settings.py

Central tunables for the ATS layout pipeline. Every threshold here is
expressed *relative* to page/content geometry or font medians rather than as
absolute pixel values, so the parser adapts to any page size or template
without hardcoded magic numbers. Heading text->canonical mappings live
separately in :mod:`section_detection.section_keywords`.
"""

from __future__ import annotations

PARSER_VERSION = "2.0-layout-ats"

# ---------------------------------------------------------------------------
# Column detection (Stage 2)
# ---------------------------------------------------------------------------
COLUMN_SETTINGS: dict[str, float] = {
    # A block wider than this fraction of the content width is treated as a
    # full-width header/footer band and excluded from column gutter analysis.
    "fullwidth_ratio": 0.72,
    # A vertical whitespace gap must be at least this fraction of the content
    # width to count as a real column gutter (adaptive, not pixel-based).
    "gutter_min_ratio": 0.06,
    # Two columns whose widths differ by more than this ratio are classified
    # as a sidebar layout rather than a balanced two-column layout.
    "sidebar_width_ratio": 0.55,
    # A gutter is only a column separator if blocks on both sides share at
    # least this fraction of vertical overlap (guards against paragraph gaps).
    "min_vertical_overlap": 0.15,
    # Minimum blocks in a band before column analysis is attempted.
    "min_blocks_for_columns": 4,
    # A vertical gap larger than this multiple of the median line height starts
    # a new band (separates stacked regions even when all lines are narrow).
    "band_gap_factor": 1.8,
}

# ---------------------------------------------------------------------------
# Reading-order reconstruction (Stage 3)
# ---------------------------------------------------------------------------
READING_ORDER_SETTINGS: dict[str, float] = {
    # Consecutive same-column blocks closer than this multiple of the line's
    # font size are merged into one logical block (rejoins wrapped paragraphs).
    "merge_gap_font_multiple": 0.6,
}

# ---------------------------------------------------------------------------
# Layout-aware heading detection (Stage 4)
# ---------------------------------------------------------------------------
# These weights are *added on top of* the text-shape score computed from
# section_keywords.HEADING_HEURISTICS.
LAYOUT_HEADING_SETTINGS: dict[str, float] = {
    "score_threshold": 0.55,
    # Font larger than page median * this ratio is a strong heading signal.
    "font_large_ratio": 1.15,
    "weight_font_large": 0.20,
    "weight_font_larger": 0.30,   # >= font_larger_ratio
    "font_larger_ratio": 1.4,
    "weight_bold": 0.18,
    "weight_block_start": 0.06,   # first line of its block (preceded by whitespace)
}

# ---------------------------------------------------------------------------
# Section validation (Stage 8)
# ---------------------------------------------------------------------------
VALIDATION_SETTINGS: dict[str, int] = {
    "summary_min_words": 12,       # summaries are paragraphs
    "skills_item_max_words": 12,   # skills entries are short
    "min_content_words": 2,
    "confidence_floor": 0.1,
}

# Signal vocabularies used to sanity-check section *content* (lowercased).
# These are content hints, not headings, so they live here rather than in the
# heading keyword config.
CONTENT_SIGNALS: dict[str, tuple[str, ...]] = {
    "education": (
        "university", "college", "school", "institute", "academy",
        "bachelor", "master", "b.tech", "btech", "m.tech", "mtech",
        "bsc", "msc", "b.e", "m.e", "phd", "diploma", "degree", "gpa",
        "class", "ssc", "hsc", "secondary",
    ),
    "experience": (
        "intern", "engineer", "developer", "manager", "analyst", "consultant",
        "lead", "specialist", "officer", "associate", "present", "inc", "ltd",
        "studio", "technologies", "solutions", "company",
    ),
}
