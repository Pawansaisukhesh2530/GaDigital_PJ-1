"""
text_normalizer.py

Cleans raw extracted resume text without changing its meaning, wording, or
line order. This is a pure text-cleanup step — it never rewrites,
summarizes, reorders, or removes actual resume content. It only fixes
formatting noise introduced by PDF/DOCX extraction (stray spaces, odd
Unicode, inconsistent bullets/quotes/hyphens) and tidies up a few very
common formatting mistakes in emails, phone numbers, and dates.

This module has no dependency on the CLI, the logger, or section detection —
it can be reused as-is by a future API layer or LLM-parsing milestone.
"""

from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass, field

# ---------------------------------------------------------------------------
# Character normalization tables
# ---------------------------------------------------------------------------

# Curly/typographic quotes -> straight ASCII quotes.
_QUOTE_MAP: dict[str, str] = {
    "\u2018": "'",  # left single quote
    "\u2019": "'",  # right single quote / apostrophe
    "\u201a": "'",  # single low-9 quote
    "\u201b": "'",  # single high-reversed-9 quote
    "\u201c": '"',  # left double quote
    "\u201d": '"',  # right double quote
    "\u201e": '"',  # double low-9 quote
    "\u201f": '"',  # double high-reversed-9 quote
    "\u2032": "'",  # prime
    "\u2033": '"',  # double prime
}

# Dash/hyphen variants -> a plain ASCII hyphen.
_HYPHEN_MAP: dict[str, str] = {
    "\u2010": "-",  # hyphen
    "\u2011": "-",  # non-breaking hyphen
    "\u2012": "-",  # figure dash
    "\u2013": "-",  # en dash
    "\u2014": "-",  # em dash
    "\u2015": "-",  # horizontal bar
    "\u2212": "-",  # minus sign
}

# Bullet glyph variants recognized at the start of a line.
_BULLET_CHARS = "•‣◦▪▫▸►●○∙·‒–—*"

# Invisible / zero-width characters that carry no visual meaning.
_INVISIBLE_CHARS = (
    "\u200b"  # zero-width space
    "\u200c"  # zero-width non-joiner
    "\u200d"  # zero-width joiner
    "\u200e"  # left-to-right mark
    "\u200f"  # right-to-left mark
    "\ufeff"  # BOM
    "\xa0"    # non-breaking space (normalized to a regular space instead)
)

# Stray control characters occasionally left behind by PDF text extraction
# (form feed, vertical tab, etc.) — newline and tab are handled separately.
_CONTROL_CHAR_PATTERN = re.compile(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]")

_MONTH_MAP: dict[str, str] = {
    "january": "Jan", "jan": "Jan",
    "february": "Feb", "feb": "Feb",
    "march": "Mar", "mar": "Mar",
    "april": "Apr", "apr": "Apr",
    "may": "May",
    "june": "Jun", "jun": "Jun",
    "july": "Jul", "jul": "Jul",
    "august": "Aug", "aug": "Aug",
    "september": "Sep", "sept": "Sep", "sep": "Sep",
    "october": "Oct", "oct": "Oct",
    "november": "Nov", "nov": "Nov",
    "december": "Dec", "dec": "Dec",
}

_NUMERIC_MONTHS = (
    "Jan", "Feb", "Mar", "Apr", "May", "Jun",
    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
)

_EMAIL_PATTERN = re.compile(
    r"[A-Za-z0-9._%+\-]+\s*@\s*[A-Za-z0-9.\-]+(?:\s*\.\s*[A-Za-z]{2,})+"
)

# "+<country code> <group> <group> ..." e.g. "+91 98765 43210".
_PHONE_CC_PATTERN = re.compile(r"(\+\d{1,3})\s+((?:\d{2,5}\s+){1,3}\d{2,5})(?!\d)")

# Textual month followed by a 4-digit year, e.g. "January 2024", "Jan. 2024".
_DATE_TEXT_PATTERN = re.compile(r"\b([A-Za-z]{3,9})\.?\s+(\d{4})\b")

# Numeric month/year, e.g. "01/2024" or "01-2024".
_DATE_NUMERIC_PATTERN = re.compile(r"(?<!\d)(0?[1-9]|1[0-2])[/\-](\d{4})(?!\d)")

_BULLET_LINE_PATTERN = re.compile(rf"^(\s*)[{re.escape(_BULLET_CHARS)}]\s*")

_REPEATED_SPACE_PATTERN = re.compile(r"[ \t]{2,}")
_EXCESSIVE_BLANK_LINES_PATTERN = re.compile(r"\n{3,}")


@dataclass
class NormalizationResult:
    """Outcome of a text normalization pass.

    Attributes:
        success: Whether normalization completed without a fatal error.
        text: The cleaned text (falls back to the original input on failure).
        warnings: Non-fatal issues encountered while cleaning (e.g. empty input).
    """

    success: bool
    text: str
    warnings: list[str] = field(default_factory=list)


class TextNormalizer:
    """Cleans extracted resume text while preserving its content and order.

    The normalizer never rewrites or summarizes text. Every step is a
    reversible, meaning-preserving formatting fix (whitespace, Unicode,
    punctuation, and a few common email/phone/date formatting slips).
    """

    def normalize(self, text: str) -> NormalizationResult:
        """Run the full normalization pipeline over ``text``.

        Never raises. If an unexpected error occurs mid-pipeline, the
        original input is returned unchanged along with a warning describing
        the failure, so callers can continue processing instead of crashing.
        """
        warnings: list[str] = []

        if text is None or not text.strip():
            warnings.append("Input text was empty; nothing to normalize.")
            return NormalizationResult(success=True, text="", warnings=warnings)

        try:
            cleaned = text
            cleaned = self._normalize_unicode(cleaned)
            cleaned = self._remove_invisible_characters(cleaned)
            cleaned = self._remove_pdf_artifacts(cleaned)
            cleaned = self._normalize_tabs(cleaned)
            cleaned = self._normalize_quotes(cleaned)
            cleaned = self._normalize_hyphens(cleaned)
            cleaned = self._normalize_bullets(cleaned)
            cleaned = self._collapse_repeated_spaces(cleaned)
            cleaned = self._collapse_blank_lines(cleaned)
            cleaned = self._normalize_emails(cleaned)
            cleaned = self._normalize_phones(cleaned)
            cleaned = self._normalize_dates(cleaned)
            cleaned = cleaned.strip()
        except Exception as exc:  # noqa: BLE001 - normalization must never crash the pipeline
            warnings.append(f"Normalization failed, returning original text unchanged: {exc}")
            return NormalizationResult(success=False, text=text, warnings=warnings)

        if not cleaned:
            warnings.append("Normalized text is empty after cleaning.")

        return NormalizationResult(success=True, text=cleaned, warnings=warnings)

    # -- Character-level cleanup -------------------------------------------------

    @staticmethod
    def _normalize_unicode(text: str) -> str:
        """Normalize Unicode to NFKC (also collapses ligatures like 'fi' -> 'fi')."""
        return unicodedata.normalize("NFKC", text)

    @staticmethod
    def _remove_invisible_characters(text: str) -> str:
        """Strip zero-width/invisible characters; turn NBSP into a normal space."""
        text = text.replace("\xa0", " ")
        for char in _INVISIBLE_CHARS:
            if char != "\xa0":
                text = text.replace(char, "")
        return text

    @staticmethod
    def _remove_pdf_artifacts(text: str) -> str:
        """Remove stray control characters (form feed, soft hyphen, etc.)."""
        text = text.replace("\xad", "")  # soft hyphen
        return _CONTROL_CHAR_PATTERN.sub("", text)

    @staticmethod
    def _normalize_tabs(text: str) -> str:
        """Convert tab characters into a single space."""
        return text.replace("\t", " ")

    @staticmethod
    def _normalize_quotes(text: str) -> str:
        """Map curly/typographic quotation marks to straight ASCII quotes."""
        for original, replacement in _QUOTE_MAP.items():
            text = text.replace(original, replacement)
        return text

    @staticmethod
    def _normalize_hyphens(text: str) -> str:
        """Map en/em dashes and similar glyphs to a plain ASCII hyphen."""
        for original, replacement in _HYPHEN_MAP.items():
            text = text.replace(original, replacement)
        return text

    @staticmethod
    def _normalize_bullets(text: str) -> str:
        """Map varied bullet glyphs at the start of a line to a plain '-'."""
        lines = text.split("\n")
        normalized_lines = [_BULLET_LINE_PATTERN.sub(r"\1- ", line) for line in lines]
        return "\n".join(normalized_lines)

    # -- Whitespace cleanup -------------------------------------------------

    @staticmethod
    def _collapse_repeated_spaces(text: str) -> str:
        """Collapse runs of spaces/tabs into one, trimming each line's edges."""
        lines = text.split("\n")
        cleaned_lines = [_REPEATED_SPACE_PATTERN.sub(" ", line).strip() for line in lines]
        return "\n".join(cleaned_lines)

    @staticmethod
    def _collapse_blank_lines(text: str) -> str:
        """Collapse 3+ consecutive newlines into a single blank line."""
        return _EXCESSIVE_BLANK_LINES_PATTERN.sub("\n\n", text)

    # -- Field-formatting cleanup -------------------------------------------------

    @staticmethod
    def _normalize_emails(text: str) -> str:
        """Remove stray spaces inside email addresses, e.g. 'john @ gmail .com'."""

        def _clean(match: re.Match[str]) -> str:
            return re.sub(r"\s+", "", match.group(0))

        return _EMAIL_PATTERN.sub(_clean, text)

    @staticmethod
    def _normalize_phones(text: str) -> str:
        """Remove stray spaces inside a phone number's local digit groups.

        Only numbers written with a leading '+<country code>' are touched
        (e.g. '+91 98765 43210' -> '+91 9876543210'). Other formats (local
        numbers, parenthesized area codes) are intentionally left as-is —
        see README known limitations.
        """

        def _clean(match: re.Match[str]) -> str:
            digits = re.sub(r"\s+", "", match.group(2))
            return f"{match.group(1)} {digits}"

        return _PHONE_CC_PATTERN.sub(_clean, text)

    @staticmethod
    def _normalize_dates(text: str) -> str:
        """Normalize month formatting to a consistent 'Mon YYYY' style.

        Handles textual months ('January 2024', 'Jan. 2024') and numeric
        month/year ('01/2024'). The year and all surrounding text (including
        day numbers, ranges, and 'Present') are left untouched.
        """

        def _clean_text_month(match: re.Match[str]) -> str:
            word = match.group(1).lower()
            abbrev = _MONTH_MAP.get(word)
            if abbrev is None:
                return match.group(0)  # not a month word — leave untouched
            return f"{abbrev} {match.group(2)}"

        text = _DATE_TEXT_PATTERN.sub(_clean_text_month, text)

        def _clean_numeric_month(match: re.Match[str]) -> str:
            index = int(match.group(1)) - 1
            return f"{_NUMERIC_MONTHS[index]} {match.group(2)}"

        text = _DATE_NUMERIC_PATTERN.sub(_clean_numeric_month, text)
        return text
