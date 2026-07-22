"""
section_postprocess.py

Deterministic post-processing for LLM section responses, applied AFTER JSON
parsing but BEFORE schema validation. This is part of the Resume Intelligence
Layer only — it never touches the ATS parser, the ATS ``normalization`` package,
or ``sections.json``.

Two responsibilities:

1. Defensive top-level key normalization (Parts 1, 2, 14, 15)
   The model occasionally wraps a section under a near-miss key
   (e.g. ``{"language": [...]}`` instead of ``{"languages": [...]}``). Rather
   than let the validator silently discard otherwise-valid content, we map a
   small, explicit, section-specific set of aliases back to the canonical
   shape and record every remap so it is auditable.

2. Deterministic date normalization + range repair (Parts 7, 8, 9)
   Harmless date-formatting differences ("2024- 2028", "June - 2025") are
   normalized to a canonical ``YYYY`` / ``YYYY-MM`` form and split ranges are
   repaired BEFORE validation, so date formatting alone never triggers a retry.
   Days are never invented.

All mappings are explicit and conservative; nothing is fuzzily guessed.
"""

from __future__ import annotations

import re

from schemas.resume_schema import SKILL_CATEGORIES

# ---------------------------------------------------------------------------
# Explicit, section-specific key aliases (no broad fuzzy matching)
# ---------------------------------------------------------------------------

_ARRAY_ALIASES: dict[str, tuple[str, ...]] = {
    "languages": ("language", "spoken_languages", "language_skills", "languages_spoken"),
    "projects": ("project", "project_list", "projects_list"),
    "education": ("educations", "education_history", "qualifications"),
    "experience": ("experiences", "work_experience", "employment", "experience_history"),
}
_SKILL_ALIASES: tuple[str, ...] = ("skills", "skill", "skill_set", "skillset")

_BULLET_PREFIXES = "•◦‣·*-–—"

_MONTHS: dict[str, int] = {
    "jan": 1, "january": 1, "feb": 2, "february": 2, "mar": 3, "march": 3,
    "apr": 4, "april": 4, "may": 5, "jun": 6, "june": 6, "jul": 7, "july": 7,
    "aug": 8, "august": 8, "sep": 9, "sept": 9, "september": 9, "oct": 10,
    "october": 10, "nov": 11, "november": 11, "dec": 12, "december": 12,
}
_OPEN_ENDED = {"present", "current", "ongoing"}


# ---------------------------------------------------------------------------
# Public entry point
# ---------------------------------------------------------------------------


def postprocess_payload(section_key: str, kind: str, parsed: object) -> tuple[object, list[dict]]:
    """Canonicalize keys and normalize dates. Returns (payload, normalizations)."""
    normalizations: list[dict] = []
    payload = _canonicalize_key(section_key, kind, parsed, normalizations)

    if section_key in ("education", "experience"):
        for item in _iter_dict_items(payload):
            normalizations.extend(_normalize_item_dates(section_key, item))

    return payload, normalizations


def count_project_candidates(raw_text: str) -> int:
    """Conservatively count project *header* lines in the raw source text.

    Continuation / bullet lines are excluded so multi-line project descriptions
    on clean resumes do not inflate the count and trigger false retries.
    """
    count = 0
    for line in raw_text.splitlines():
        stripped = line.strip()
        if not stripped:
            continue
        if stripped[0] in _BULLET_PREFIXES:
            continue
        count += 1
    return count


# ---------------------------------------------------------------------------
# Key canonicalization
# ---------------------------------------------------------------------------


def _canonicalize_key(section_key: str, kind: str, parsed: object, norms: list[dict]) -> object:
    if not isinstance(parsed, dict):
        return parsed

    if kind == "array":
        if isinstance(parsed.get(section_key), list):
            return parsed[section_key]
        for alias in _ARRAY_ALIASES.get(section_key, ()):  # explicit aliases only
            if isinstance(parsed.get(alias), list):
                norms.append({"section": section_key, "from": alias, "to": section_key, "type": "key_alias"})
                return parsed[alias]
        return parsed  # leave for the validator (single-item dicts are wrapped there)

    if section_key == "skills":
        if _looks_like_categories(parsed):
            return parsed
        for alias in _SKILL_ALIASES:
            value = parsed.get(alias)
            if isinstance(value, dict) and _looks_like_categories(value):
                norms.append({"section": "skills", "from": alias, "to": "skills", "type": "key_alias"})
                return value
        return parsed

    return parsed


def _looks_like_categories(data: object) -> bool:
    return isinstance(data, dict) and any(category in data for category in SKILL_CATEGORIES)


def _iter_dict_items(payload: object) -> list[dict]:
    if isinstance(payload, list):
        return [item for item in payload if isinstance(item, dict)]
    if isinstance(payload, dict):
        if any(field in payload for field in ("start_date", "end_date")):
            return [payload]  # a single un-wrapped item object
    return []


# ---------------------------------------------------------------------------
# Date normalization + range repair
# ---------------------------------------------------------------------------


def _normalize_item_dates(section: str, item: dict) -> list[dict]:
    norms: list[dict] = []
    orig_start = _as_text(item.get("start_date"))
    orig_end = _as_text(item.get("end_date"))

    new_start, new_end, repaired = _normalize_range(orig_start, orig_end)
    kind = "date_range_split" if repaired else "date_format"

    if new_start != orig_start:
        item["start_date"] = new_start
        norms.append({"section": section, "field": "start_date", "from": orig_start, "to": new_start, "type": kind})
    if new_end != orig_end:
        item["end_date"] = new_end
        norms.append({"section": section, "field": "end_date", "from": orig_end, "to": new_end, "type": kind})
    return norms


def _normalize_range(start_raw: str, end_raw: str) -> tuple[str, str, bool]:
    """Return (start, end, repaired).

    Repairs a full date range that the model placed inside a single field. This
    happens whether or not ``end_date`` is also populated (models frequently
    emit e.g. start="2022 – 2024" AND end="2024"), so the split is attempted on
    ``start_raw`` unconditionally — deterministically fixing the value before
    validation and avoiding an otherwise-wasted LLM retry.
    """
    start_split = _split_range(start_raw) if start_raw else None
    if start_split is not None:
        start = start_split[0]
        # Keep an explicit end when present; otherwise use the range's end.
        end = normalize_single_date(end_raw) or start_split[1]
        return start, end, True

    # A range accidentally placed in end_date: keep its later bound as the end.
    end_split = _split_range(end_raw) if end_raw else None
    if end_split is not None:
        return normalize_single_date(start_raw), end_split[1], True

    return normalize_single_date(start_raw), normalize_single_date(end_raw), False


def _split_range(value: str) -> tuple[str, str] | None:
    """Split ``value`` into (start, end) only if BOTH sides are full dates.

    This distinguishes a true range ("2024 - 2028") from a single month-year
    ("June - 2025"), where the left side alone is not a complete date.
    """
    parts = re.split(r"\s*(?:–|—|\bto\b)\s*", value, flags=re.IGNORECASE)
    if len(parts) != 2:
        parts = re.split(r"\s*-\s*", value)
    if len(parts) != 2:
        return None

    left = normalize_single_date(parts[0].strip())
    right = normalize_single_date(parts[1].strip())
    if _is_full_date(left) and _is_full_date(right):
        return left, right
    return None


def _is_full_date(value: str) -> bool:
    return bool(re.match(r"^\d{4}(-\d{2})?$", value)) or value in _OPEN_ENDED


def normalize_single_date(value: str) -> str:
    """Normalize one date token to ``YYYY`` or ``YYYY-MM``. Never invents a day."""
    text = _as_text(value)
    if not text:
        return ""

    low = text.lower()
    if low in _OPEN_ENDED:
        return low
    if low == "now":
        return "present"

    # Month name + year: "June 2025", "Oct- 2025", "October-2025", "June - 2025"
    match = re.match(r"^([A-Za-z]{3,9})\.?[\s\-/]+(\d{4})$", text)
    if match:
        month = _MONTHS.get(match.group(1).lower())
        if month:
            return f"{match.group(2)}-{month:02d}"

    # Numeric MM/YYYY or MM-YYYY
    match = re.match(r"^(0?[1-9]|1[0-2])[/\-](\d{4})$", text)
    if match:
        return f"{match.group(2)}-{int(match.group(1)):02d}"

    # YYYY-MM or YYYY/MM
    match = re.match(r"^(\d{4})[/\-](0?[1-9]|1[0-2])$", text)
    if match:
        return f"{match.group(1)}-{int(match.group(2)):02d}"

    # Full ISO with a day -> reduce to canonical YYYY-MM (day is not invented, only dropped)
    match = re.match(r"^(\d{4})[/\-](0?[1-9]|1[0-2])[/\-]\d{1,2}$", text)
    if match:
        return f"{match.group(1)}-{int(match.group(2)):02d}"

    # Year only
    if re.match(r"^\d{4}$", text):
        return text

    return text  # leave anything else untouched (genuine validation follows)


def _as_text(value: object) -> str:
    if value is None:
        return ""
    if isinstance(value, (str, int, float)):
        return str(value).strip()
    return ""
