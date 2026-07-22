"""
section_keywords.py

Single source of truth for section-detection configuration:

* ``CANONICAL_SECTION_ORDER`` — the canonical section keys and their preferred
  output order.
* ``SECTION_KEYWORDS`` — every heading text variation that maps to a canonical
  section (Stage 4 heading normalization).
* ``HEADING_HEURISTICS`` — the tunable weights/thresholds used by the heading
  detector (Stage 3).
* ``VALIDATION_RULES`` — thresholds used by the section validator (Stage 6).

All mappings and tuning live here so they are never scattered across the
detection logic. New templates are supported by editing this file only.
"""

from __future__ import annotations

# ---------------------------------------------------------------------------
# Canonical sections
# ---------------------------------------------------------------------------

# Canonical output keys, in the order they should appear in the final
# structured output when present. "others" always comes last.
CANONICAL_SECTION_ORDER: tuple[str, ...] = (
    "personal_information",
    "summary",
    "experience",
    "education",
    "skills",
    "projects",
    "certifications",
    "achievements",
    "languages",
    "interests",
    "volunteering",
    "publications",
    "references",
    "others",
)

# Sections that are normally expected to appear near the top of a resume.
# Used only for a *soft* ordering sanity check (PDF extraction frequently
# reorders content, so a violation is a warning, never an error).
TOP_SECTIONS: tuple[str, ...] = ("personal_information", "summary")

# Sections whose body is expected to be substantial prose/lists. Used by the
# validator to flag suspiciously tiny or empty content.
CONTENT_HEAVY_SECTIONS: tuple[str, ...] = (
    "summary",
    "experience",
    "education",
    "projects",
)

# Sections expected to be short list-style items rather than paragraphs.
LIST_STYLE_SECTIONS: tuple[str, ...] = ("skills", "languages", "interests")


# ---------------------------------------------------------------------------
# Heading text variations -> canonical key (Stage 4)
# ---------------------------------------------------------------------------

# Keys are the canonical section; values are heading variations already reduced
# to their lookup form (lowercase, alphanumeric only). See
# ``HeadingNormalizer.reduce`` for how a raw line is reduced to this form.
SECTION_KEYWORDS: dict[str, tuple[str, ...]] = {
    "personal_information": (
        "personaldetails",
        "personalinformation",
        "personalinfo",
        "contact",
        "contactinformation",
        "contactdetails",
        "contactme",
    ),
    "summary": (
        "profile",
        "summary",
        "professionalsummary",
        "careersummary",
        "objective",
        "careerobjective",
        "professionalobjective",
        "profilesummary",
        "aboutme",
        "about",
    ),
    "experience": (
        "workexperience",
        "experience",
        "employment",
        "employmenthistory",
        "professionalexperience",
        "workhistory",
        "careerhistory",
        "professionalbackground",
    ),
    "education": (
        "education",
        "academicdetails",
        "academicbackground",
        "educationalbackground",
        "educationandtraining",
        "qualifications",
        "academicqualifications",
    ),
    "skills": (
        "skills",
        "technicalskills",
        "coreskills",
        "keyskills",
        "softskills",
        "skillsandexpertise",
        "areasofexpertise",
        "competencies",
        "corecompetencies",
    ),
    "projects": (
        "projects",
        "keyprojects",
        "academicprojects",
        "personalprojects",
        "notableprojects",
    ),
    "certifications": (
        "certifications",
        "certification",
        "certificates",
        "licensesandcertifications",
        "professionalcertifications",
        "courses",
        "coursesandcertifications",
    ),
    "achievements": (
        "achievements",
        "awards",
        "awardsandactivities",
        "honorsandawards",
        "accomplishments",
        "activities",
    ),
    "languages": (
        "languages",
        "languageproficiency",
        "languagesknown",
    ),
    "interests": (
        "interests",
        "hobbies",
        "hobbiesandinterests",
        "interestsandhobbies",
    ),
    "volunteering": (
        "volunteering",
        "volunteerexperience",
        "volunteerwork",
        "communityservice",
    ),
    "publications": (
        "publications",
        "researchpublications",
        "papers",
    ),
    "references": (
        "reference",
        "references",
        "referee",
        "referees",
    ),
}


def build_keyword_lookup() -> dict[str, str]:
    """Flatten ``SECTION_KEYWORDS`` into a single {variation: canonical_key} map."""
    lookup: dict[str, str] = {}
    for canonical_key, variations in SECTION_KEYWORDS.items():
        for variation in variations:
            lookup[variation] = canonical_key
    return lookup


# ---------------------------------------------------------------------------
# Heading detection heuristics (Stage 3)
# ---------------------------------------------------------------------------

# A line is classified as a heading when its combined heuristic score is at
# least ``score_threshold``. Weights are additive; the final score is clamped
# to [0.0, 1.0]. Tune here without touching detector code.
HEADING_HEURISTICS: dict[str, float] = {
    # Hard gates -----------------------------------------------------------
    "max_heading_words": 6,      # lines longer than this are never headings
    "max_heading_chars": 60,     # very long lines are never headings
    "score_threshold": 0.50,     # minimum score to be considered a heading

    # Positive signals -----------------------------------------------------
    "weight_keyword_match": 0.55,   # matches the keyword dictionary
    "weight_few_words": 0.15,       # 1-3 words
    "weight_some_words": 0.07,      # 4-6 words
    "weight_all_caps": 0.15,        # ALL UPPERCASE letters
    "weight_title_case": 0.08,      # Title Case Words
    "weight_trailing_colon": 0.10,  # ends with ':'
    "weight_no_end_punct": 0.08,    # does not end in . , ; etc.
    "weight_short_chars": 0.05,     # within max_heading_chars

    # Negative signals -----------------------------------------------------
    "penalty_sentence_end": 0.25,   # ends with . ! ?
    "penalty_prose": 0.12,          # looks like a sentence (mid-line period / many commas)
    "penalty_long": 0.15,           # exceeds max_heading_chars
    "penalty_digits_heavy": 0.10,   # mostly digits (dates, phone numbers)
}


# ---------------------------------------------------------------------------
# Validation thresholds (Stage 6)
# ---------------------------------------------------------------------------

VALIDATION_RULES: dict[str, int] = {
    "max_section_lines": 80,          # a section larger than this is suspicious
    "min_content_words": 3,           # content-heavy section below this is suspicious
    "list_item_max_words": 18,        # a list-style line longer than this looks like prose
    "confidence_penalty_pct": 25,     # % confidence reduction per failed validation
}
