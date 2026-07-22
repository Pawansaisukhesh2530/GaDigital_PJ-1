from __future__ import annotations

import re

from schemas.resume_schema import (
    EDUCATION_FIELDS,
    EXPERIENCE_FIELDS,
    LANGUAGE_FIELDS,
    PERSONAL_FIELDS,
    PROJECT_FIELDS,
    SKILL_CATEGORIES,
)

_EMAIL_RE = re.compile(r"^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$")
_PHONE_RE = re.compile(r"^[+()\-\s0-9]{7,}$")
# Permissive date shapes: year, month-year, numeric, ISO, or a "present" token.
_DATE_RE = re.compile(
    r"^(present|current|ongoing|"
    r"\d{4}|"
    r"(0?[1-9]|1[0-2])[/\-]\d{4}|"
    r"\d{4}[/\-](0?[1-9]|1[0-2])([/\-]\d{1,2})?|"
    r"[A-Za-z]{3,9}\.?\s+\d{4})$",
    re.IGNORECASE,
)

Result = tuple[bool, list[str], object]


def is_valid_email(value: str) -> bool:
    """True if ``value`` is a syntactically valid email address."""
    return bool(value) and bool(_EMAIL_RE.match(value.strip()))


def is_valid_phone(value: str) -> bool:
    """True if ``value`` looks like a phone number."""
    return bool(value) and bool(_PHONE_RE.match(value.strip()))


def validate_section(key: str, kind: str, data: object) -> Result:
    """Dispatch to the correct validator for ``key``/``kind``."""
    if key == "personal":
        return validate_personal(data)
    if key == "skills":
        return validate_skills(data)
    if kind == "text":
        return validate_summary(data)
    if key == "education":
        return _validate_array(data, EDUCATION_FIELDS, "education", _normalize_education_item)
    if key == "experience":
        return _validate_array(data, EXPERIENCE_FIELDS, "experience", _normalize_experience_item)
    if key == "projects":
        return _validate_array(data, PROJECT_FIELDS, "projects", _normalize_project_item)
    if key == "languages":
        return _validate_array(data, LANGUAGE_FIELDS, "languages", _normalize_language_item)
    return False, [f"Unknown section '{key}'."], data


# ---------------------------------------------------------------------------
# Scalar helpers
# ---------------------------------------------------------------------------


def _as_str(value: object) -> str:
    if value is None:
        return ""
    if isinstance(value, (str, int, float)):
        return str(value).strip()
    return ""


def _as_list(value: object) -> list:
    if value is None:
        return []
    if isinstance(value, list):
        return value
    if isinstance(value, str) and value.strip():
        return [value.strip()]
    return []


def _clean_str_list(value: object) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for item in _as_list(value):
        text = _as_str(item)
        low = text.lower()
        if text and low not in seen:
            seen.add(low)
            out.append(text)
    return out


def _valid_date(value: str) -> bool:
    return value == "" or bool(_DATE_RE.match(value.strip()))


# ---------------------------------------------------------------------------
# Personal
# ---------------------------------------------------------------------------


def validate_personal(data: object) -> Result:
    errors: list[str] = []
    if not isinstance(data, dict):
        return False, ["personal must be a JSON object."], {f: "" for f in PERSONAL_FIELDS}

    normalized = {field: _as_str(data.get(field)) for field in PERSONAL_FIELDS}

    if normalized["email"] and not _EMAIL_RE.match(normalized["email"]):
        errors.append(f"Invalid email: {normalized['email']!r}.")
    if normalized["phone"] and not _PHONE_RE.match(normalized["phone"]):
        errors.append(f"Invalid phone: {normalized['phone']!r}.")

    return (not errors), errors, normalized


# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------


def validate_summary(data: object) -> Result:
    if isinstance(data, dict):  # tolerate {"summary": "..."}
        data = data.get("summary", "")
    if data is None:
        return True, [], ""
    if not isinstance(data, str):
        return False, ["summary must be a string."], _as_str(data)
    return True, [], data.strip()


# ---------------------------------------------------------------------------
# Skills
# ---------------------------------------------------------------------------


def validate_skills(data: object) -> Result:
    errors: list[str] = []
    if not isinstance(data, dict):
        return False, ["skills must be a JSON object."], {c: [] for c in SKILL_CATEGORIES}

    normalized = {category: _clean_str_list(data.get(category)) for category in SKILL_CATEGORIES}
    return True, errors, normalized


# ---------------------------------------------------------------------------
# Array sections
# ---------------------------------------------------------------------------


def _validate_array(data: object, fields, label: str, normalizer) -> Result:
    errors: list[str] = []
    if data is None:
        return True, [], []
    if isinstance(data, dict):  # tolerate {"education": [...]}
        data = data.get(label, data.get("items", [data]))
    if not isinstance(data, list):
        return False, [f"{label} must be a JSON array."], []

    normalized: list[dict] = []
    seen: set[str] = set()
    for index, item in enumerate(data):
        if not isinstance(item, dict):
            errors.append(f"{label}[{index}] must be an object.")
            continue
        clean, item_errors = normalizer(item)
        errors.extend(f"{label}[{index}]: {e}" for e in item_errors)
        signature = repr(sorted(clean.items(), key=lambda kv: kv[0]))
        if signature in seen:
            continue  # drop duplicate entry
        seen.add(signature)
        if any(_as_str(v) or v is True or (isinstance(v, list) and v) for v in clean.values()):
            normalized.append(clean)

    return (not errors), errors, normalized


def _normalize_education_item(item: dict) -> tuple[dict, list[str]]:
    clean = {field: _as_str(item.get(field)) for field in EDUCATION_FIELDS}
    errors: list[str] = []
    for date_field in ("start_date", "end_date"):
        if not _valid_date(clean[date_field]):
            errors.append(f"invalid {date_field} {clean[date_field]!r}")
    return clean, errors


def _normalize_experience_item(item: dict) -> tuple[dict, list[str]]:
    clean = {
        "job_title": _as_str(item.get("job_title")),
        "company": _as_str(item.get("company")),
        "location": _as_str(item.get("location")),
        "employment_type": _as_str(item.get("employment_type")),
        "start_date": _as_str(item.get("start_date")),
        "end_date": _as_str(item.get("end_date")),
        "currently_working": bool(item.get("currently_working", False)),
        "description": _clean_str_list(item.get("description")),
    }
    errors: list[str] = []
    for date_field in ("start_date", "end_date"):
        if not _valid_date(clean[date_field]):
            errors.append(f"invalid {date_field} {clean[date_field]!r}")
    return clean, errors


def _normalize_project_item(item: dict) -> tuple[dict, list[str]]:
    clean = {
        "project_name": _as_str(item.get("project_name")),
        "description": _as_str(item.get("description")),
        "technologies": _clean_str_list(item.get("technologies")),
    }
    return clean, []


def _normalize_language_item(item: dict) -> tuple[dict, list[str]]:
    clean = {
        "language": _as_str(item.get("language")),
        "proficiency": _as_str(item.get("proficiency")),
    }
    return clean, []
