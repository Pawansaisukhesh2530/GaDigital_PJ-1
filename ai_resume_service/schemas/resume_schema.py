"""
resume_schema.py

Canonical target schema for the structured resume and the section map that
drives section-aware extraction.

The schema mirrors the milestone specification exactly. ``SECTION_SPECS``
declares, for each LLM-extracted section, which ``sections.json`` key supplies
the raw text, which prompt template to use, and the output shape (used by the
validator, merger, and offline mock). Sections not extracted by the LLM
(certifications, achievements, interests, references) are filled
deterministically from the parser output so no information is invented.
"""

from __future__ import annotations

from dataclasses import dataclass
import copy

# ---------------------------------------------------------------------------
# Empty target schema (Part 6 of the specification)
# ---------------------------------------------------------------------------

_EMPTY_RESUME: dict = {
    "personal": {
        "name": "",
        "designation": "",
        "email": "",
        "phone": "",
        "location": "",
        "linkedin": "",
        "github": "",
        "portfolio": "",
    },
    "summary": "",
    "education": [],
    "experience": [],
    "projects": [],
    "skills": {
        "programming_languages": [],
        "frameworks": [],
        "libraries": [],
        "databases": [],
        "tools": [],
        "cloud": [],
        "operating_systems": [],
        "core_subjects": [],
        "soft_skills": [],
        "other": [],
    },
    "certifications": [],
    "achievements": [],
    "languages": [],
    "interests": [],
    "references": [],
    "metadata": {
        "model": "",
        "parser_version": "",
        "confidence": 0.0,
    },
}

# Ordered field lists reused by validators / mock / merger.
PERSONAL_FIELDS: tuple[str, ...] = (
    "name", "designation", "email", "phone", "location", "linkedin", "github", "portfolio",
)
EDUCATION_FIELDS: tuple[str, ...] = (
    "degree", "institution", "field_of_study", "start_date", "end_date", "grade", "description",
)
EXPERIENCE_FIELDS: tuple[str, ...] = (
    "job_title", "company", "location", "employment_type",
    "start_date", "end_date", "currently_working", "description",
)
PROJECT_FIELDS: tuple[str, ...] = ("project_name", "description", "technologies")
SKILL_CATEGORIES: tuple[str, ...] = (
    "programming_languages", "frameworks", "libraries", "databases", "tools",
    "cloud", "operating_systems", "core_subjects", "soft_skills", "other",
)
LANGUAGE_FIELDS: tuple[str, ...] = ("language", "proficiency")

# Maps the parser's personal_information fields onto our schema field names.
PERSONAL_PARSER_FALLBACK: dict[str, str] = {
    "name": "name",
    "designation": "designation",
    "email": "email",
    "phone": "phone",
    "location": "location",
    "linkedin": "linkedin",
    "github": "github",
    "portfolio": "portfolio",
}


def empty_resume() -> dict:
    """Return a deep copy of the empty target schema."""
    return copy.deepcopy(_EMPTY_RESUME)


# ---------------------------------------------------------------------------
# Section-aware extraction map (Part 4)
# ---------------------------------------------------------------------------


@dataclass(frozen=True)
class SectionSpec:
    """Declares how one schema section is produced from a sections.json key.

    Attributes:
        key: The key in the structured schema (e.g. ``"education"``).
        source_key: The ``sections.json`` section whose ``raw_text`` feeds it.
        prompt_file: Section prompt template filename in ``prompts/``.
        kind: Output shape — ``object`` | ``text`` | ``array`` — used to pick
            the validator and to build empty defaults.
    """

    key: str
    source_key: str
    prompt_file: str
    kind: str


# The seven LLM-extracted sections (system prompt is shared separately).
SECTION_SPECS: tuple[SectionSpec, ...] = (
    SectionSpec("personal", "personal_information", "personal_prompt.txt", "object"),
    SectionSpec("summary", "summary", "summary_prompt.txt", "text"),
    SectionSpec("education", "education", "education_prompt.txt", "array"),
    SectionSpec("experience", "experience", "experience_prompt.txt", "array"),
    SectionSpec("projects", "projects", "projects_prompt.txt", "array"),
    SectionSpec("skills", "skills", "skills_prompt.txt", "object"),
    SectionSpec("languages", "languages", "languages_prompt.txt", "array"),
)

# Sections copied verbatim (line-split) from the parser output — never sent to
# the LLM, so nothing can be invented for them.
PASSTHROUGH_SECTIONS: dict[str, str] = {
    "certifications": "certifications",
    "achievements": "achievements",
    "interests": "interests",
    "references": "references",
}


def empty_section(kind: str) -> object:
    """Return the empty default value for a section of the given ``kind``."""
    if kind == "text":
        return ""
    if kind == "object":
        return {}
    return []
