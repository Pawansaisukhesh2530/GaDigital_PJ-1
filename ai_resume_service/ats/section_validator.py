"""
section_validator.py

Stage 8: validate each section's content against what it should look like and
adjust its confidence accordingly.

Rules are intentionally shape-based, not field-extracting:
    * summary      -> should read like a paragraph (enough words)
    * skills       -> should be mostly short entries, not prose
    * education    -> should mention institutions/degrees/years
    * experience   -> should mention roles/companies/dates
    * projects     -> should list more than a stray token
    * any section  -> a heading with no content is suspicious

Validation never edits content; it only appends warnings and lowers
confidence, so a downstream consumer (or the LLM milestone) can weigh
reliability. Confidence starts from the heading-detection score and is scaled
down per failed check.
"""

from __future__ import annotations

import re

from ats.models import DetectedSection
from ats.settings import CONTENT_SIGNALS, VALIDATION_SETTINGS

_YEAR = re.compile(r"(19|20)\d{2}")


class SectionValidator:
    """Sanity-checks sections and assigns final confidence scores."""

    def __init__(self) -> None:
        self._cfg = VALIDATION_SETTINGS

    def validate(self, sections: list[DetectedSection]) -> list[str]:
        """Validate all sections in place; return global warnings."""
        warnings: list[str] = []
        seen: set[str] = set()

        for section in sections:
            self._validate_section(section, warnings)
            if section.canonical != "others":
                if section.canonical in seen:
                    self._flag(
                        section,
                        warnings,
                        f"Duplicate '{section.canonical}' section detected.",
                    )
                seen.add(section.canonical)

        return warnings

    def _validate_section(self, section: DetectedSection, warnings: list[str]) -> None:
        body = section.raw_text.strip()
        canonical = section.canonical

        if canonical != "others" and not body:
            self._flag(section, warnings, f"Section '{canonical}' has a heading but no content.")
            return

        lines = [ln for ln in body.split("\n") if ln.strip()]
        word_count = len(body.split())

        checker = {
            "summary": self._check_summary,
            "skills": self._check_skills,
            "education": self._check_education,
            "experience": self._check_experience,
            "projects": self._check_projects,
        }.get(canonical)

        if checker is not None:
            checker(section, lines, word_count, warnings)

    # -- per-canonical checks ----------------------------------------------

    def _check_summary(self, section, lines, word_count, warnings) -> None:
        if 0 < word_count < self._cfg["summary_min_words"]:
            self._flag(
                section, warnings,
                f"Summary is short ({word_count} words); expected a paragraph.",
            )

    def _check_skills(self, section, lines, word_count, warnings) -> None:
        longest = max((len(ln.split()) for ln in lines), default=0)
        if longest > self._cfg["skills_item_max_words"]:
            self._flag(
                section, warnings,
                f"Skills contains a {longest}-word line resembling prose.",
            )

    def _check_education(self, section, lines, word_count, warnings) -> None:
        if not self._has_signal(section.raw_text, "education") and not _YEAR.search(section.raw_text):
            self._flag(
                section, warnings,
                "Education lacks institution/degree/year signals.",
            )

    def _check_experience(self, section, lines, word_count, warnings) -> None:
        if not self._has_signal(section.raw_text, "experience") and not _YEAR.search(section.raw_text):
            self._flag(
                section, warnings,
                "Experience lacks role/company/date signals.",
            )

    def _check_projects(self, section, lines, word_count, warnings) -> None:
        if word_count < self._cfg["min_content_words"]:
            self._flag(
                section, warnings,
                "Projects section is suspiciously small.",
            )

    # -- helpers ------------------------------------------------------------

    @staticmethod
    def _has_signal(text: str, kind: str) -> bool:
        lowered = text.lower()
        return any(signal in lowered for signal in CONTENT_SIGNALS.get(kind, ()))

    def _flag(self, section: DetectedSection, warnings: list[str], message: str) -> None:
        section.warnings.append(message)
        section.confidence = round(max(self._cfg["confidence_floor"], section.confidence * 0.75), 2)
        warnings.append(f"[{section.canonical}] {message}")
