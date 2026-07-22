"""
api/utils/output_sanity.py

Safe, final-output sanity checks applied at the API boundary only (Parts 13,
14). These operate on a copy of the already-validated structured resume and use
the parser's own source text for context. They never modify the frozen engine,
schema, parser, or skill classifier.
"""

from __future__ import annotations

import copy
import re

from schemas.validators import is_valid_email

# Free-mail / provider domains that must never be treated as a portfolio value
# just because they appear inside an email address.
_NON_PORTFOLIO_DOMAINS = {
    "gmail.com", "yahoo.com", "outlook.com", "hotmail.com", "live.com",
    "icloud.com", "aol.com", "protonmail.com", "mail.com", "yandex.com",
}


def _strip_url(value: str) -> str:
    v = value.strip().lower()
    v = re.sub(r"^https?://", "", v)
    v = re.sub(r"^www\.", "", v)
    return v.rstrip("/")


def _email_domain(email: str) -> str:
    return email.split("@", 1)[1].lower().strip() if "@" in email else ""


def _core_subject_context(sections_document: dict) -> str:
    """Return lowercased source text that follows a 'core subjects' marker."""
    if not isinstance(sections_document, dict):
        return ""
    combined: list[str] = []
    for key, section in sections_document.items():
        if key in ("metadata", "warnings"):
            continue
        if isinstance(section, dict):
            text = section.get("raw_text", "")
            if isinstance(text, str) and text:
                combined.append(text)
    blob = "\n".join(combined).lower()
    marker = blob.find("core subject")
    return blob[marker:] if marker != -1 else ""


def apply_output_sanity(resume: dict, sections_document: dict) -> dict:
    """Return a sanitized copy of ``resume`` (never mutates the input)."""
    result = copy.deepcopy(resume)
    personal = result.get("personal")
    if isinstance(personal, dict):
        _sanitize_personal(personal)
    _reclassify_dbms(result, sections_document)
    return result


def _sanitize_personal(personal: dict) -> None:
    email = str(personal.get("email", "") or "").strip()
    portfolio = str(personal.get("portfolio", "") or "").strip()

    # Email must pass validation; otherwise blank it (never return junk).
    if email and not is_valid_email(email):
        personal["email"] = ""
        email = ""

    # Portfolio must not merely echo the email domain or a free-mail provider.
    if portfolio:
        p = _strip_url(portfolio)
        if p in _NON_PORTFOLIO_DOMAINS or (email and p == _email_domain(email)):
            personal["portfolio"] = ""


def _reclassify_dbms(resume: dict, sections_document: dict) -> None:
    """Move 'DBMS' from databases to core_subjects when context supports it."""
    skills = resume.get("skills")
    if not isinstance(skills, dict):
        return
    databases = skills.get("databases")
    if not isinstance(databases, list):
        return

    context = _core_subject_context(sections_document)
    if "dbms" not in context:
        return  # no supporting context; leave classification untouched

    remaining = [s for s in databases if str(s).strip().lower() != "dbms"]
    if len(remaining) == len(databases):
        return  # DBMS not in databases; nothing to do

    skills["databases"] = remaining
    core = skills.setdefault("core_subjects", [])
    if isinstance(core, list) and not any(str(s).strip().lower() == "dbms" for s in core):
        core.insert(0, "DBMS")
