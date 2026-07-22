from __future__ import annotations

import re

from schemas.resume_schema import (
    PASSTHROUGH_SECTIONS,
    PERSONAL_PARSER_FALLBACK,
    empty_resume,
)
from schemas.validators import is_valid_email, is_valid_phone

# Free-mail / provider domains that must never be accepted as a "portfolio":
# the parser sometimes captures the email's domain as the portfolio by mistake.
_NON_PORTFOLIO_DOMAINS = {
    "gmail.com", "yahoo.com", "outlook.com", "hotmail.com", "live.com",
    "icloud.com", "aol.com", "protonmail.com", "mail.com", "yandex.com",
}

# Deterministic-parser fields that must NOT be rewritten by the LLM when the
# parser already has a valid value (Part 5). The LLM may only *fill* these when
# the parser value is missing or invalid.
_EXACT_FIELDS = ("email", "phone", "linkedin", "github", "portfolio")
# Soft fields: prefer the deterministic parser value when it is present; the LLM
# supplements only when the parser left them empty.
_SOFT_FIELDS = ("name", "designation", "location")

# LLM-extracted sections (for section-level provenance reporting).
_LLM_SECTIONS = ("summary", "education", "experience", "projects", "skills", "languages")

_SRC_PARSER = "deterministic_parser"
_SRC_QWEN = "qwen"


class ResponseMerger:
    """Merges section results into the final structured resume."""

    def __init__(self) -> None:
        # Field/section provenance, populated on each merge (Part 6). Not part of
        # the public structured_resume.json schema — reported separately.
        self.provenance: dict[str, str] = {}

    def merge(
        self,
        section_results: dict[str, object],
        sections_json: dict,
        *,
        model: str,
        parser_version: str,
        overall_confidence: float,
    ) -> dict:
        """Build the complete structured resume document."""
        resume = empty_resume()
        self.provenance = {}

        for key, value in section_results.items():
            if key in resume:
                resume[key] = value

        self._merge_personal(resume, sections_json)
        self._fill_passthrough(resume, sections_json)

        for section in _LLM_SECTIONS:
            self.provenance[section] = _SRC_QWEN

        resume["metadata"] = {
            "model": model,
            "parser_version": parser_version,
            "confidence": overall_confidence,
        }
        return resume

    def _merge_personal(self, resume: dict, sections_json: dict) -> None:
        """Apply a source-of-truth strategy for personal fields (Part 5/6).

        Exact fields (email/phone/URLs) prefer the deterministic parser value
        when it is valid; the LLM may only fill a genuinely missing/invalid one.
        Soft fields (name/designation/location) prefer the parser value when
        present. This prevents the LLM from silently rewriting high-confidence
        deterministic contact data.
        """
        parser_personal = sections_json.get("personal_information", {})
        if not isinstance(parser_personal, dict):
            parser_personal = {}
        llm_personal = resume.get("personal", {}) or {}

        # Resolve the email first so a bogus parser "portfolio" (== email domain)
        # can be detected and rejected in favour of the LLM's real portfolio.
        parser_email = str(parser_personal.get("email", "") or "").strip()
        llm_email = str(llm_personal.get("email", "") or "").strip()
        email_ctx = parser_email if is_valid_email(parser_email) else llm_email

        for schema_field, parser_field in PERSONAL_PARSER_FALLBACK.items():
            parser_val = str(parser_personal.get(parser_field, "") or "").strip()
            llm_val = str(llm_personal.get(schema_field, "") or "").strip()

            if schema_field in _EXACT_FIELDS:
                chosen, source = self._choose_exact(schema_field, parser_val, llm_val, email_ctx)
            else:  # soft fields
                chosen, source = self._choose_soft(parser_val, llm_val)

            llm_personal[schema_field] = chosen
            if source:
                self.provenance[f"personal.{schema_field}"] = source

        resume["personal"] = llm_personal

    @staticmethod
    def _choose_exact(field: str, parser_val: str, llm_val: str, email: str = "") -> tuple[str, str | None]:
        parser_bogus = field == "portfolio" and ResponseMerger._is_bogus_portfolio(parser_val, email)
        llm_bogus = field == "portfolio" and ResponseMerger._is_bogus_portfolio(llm_val, email)

        parser_ok = bool(parser_val) and ResponseMerger._exact_is_valid(field, parser_val) and not parser_bogus
        if parser_ok:
            return parser_val, _SRC_PARSER
        if llm_val and not llm_bogus:
            return llm_val, _SRC_QWEN
        # Neither preferred: keep a non-bogus deterministic value rather than invent.
        if parser_val and not parser_bogus:
            return parser_val, _SRC_PARSER
        return "", None

    @staticmethod
    def _is_bogus_portfolio(value: str, email: str) -> bool:
        """True if ``value`` is just the email/free-mail domain, not a real portfolio."""
        if not value:
            return False
        v = re.sub(r"^https?://", "", value.strip().lower())
        v = re.sub(r"^www\.", "", v).rstrip("/")
        if v in _NON_PORTFOLIO_DOMAINS:
            return True
        if email and "@" in email and v == email.split("@", 1)[1].lower().strip():
            return True
        return False

    @staticmethod
    def _choose_soft(parser_val: str, llm_val: str) -> tuple[str, str | None]:
        if parser_val:
            return parser_val, _SRC_PARSER
        if llm_val:
            return llm_val, _SRC_QWEN
        return "", None

    @staticmethod
    def _exact_is_valid(field: str, value: str) -> bool:
        if field == "email":
            return is_valid_email(value)
        if field == "phone":
            return is_valid_phone(value)
        # linkedin / github / portfolio: any non-empty value is acceptable.
        return bool(value)

    def _fill_passthrough(self, resume: dict, sections_json: dict) -> None:
        """Populate certifications/achievements/interests/references by line split."""
        for schema_key, source_key in PASSTHROUGH_SECTIONS.items():
            section = sections_json.get(source_key, {})
            raw_text = section.get("raw_text", "") if isinstance(section, dict) else ""
            lines = [ln.strip() for ln in raw_text.split("\n") if ln.strip()]
            if lines:
                resume[schema_key] = lines
                self.provenance[schema_key] = _SRC_PARSER
