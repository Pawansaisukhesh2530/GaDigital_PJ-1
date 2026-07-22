"""
personal_info.py

Stage 7: extract the resume header (personal information) independently.

Two responsibilities:
    * ``identify_name`` — find the candidate name/designation using the
      strongest layout signal available: the largest font on page 1. Resume
      names are almost always the biggest text on the page, which is reliable
      across templates and independent of where the name sits (sidebar,
      centered banner, or top-left). The line numbers it returns are removed
      from the section stream so the name never opens a spurious section.
    * ``build`` — assemble the final record, mining contact fields (email,
      phone, links, location) from the header band and the CONTACT/personal
      section with deterministic patterns (no AI).

Keeping this separate is what stops contact lines and the name from being
swallowed into Summary.
"""

from __future__ import annotations

import re

from ats.models import OrderedLine

_EMAIL = re.compile(r"[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}")
_PHONE = re.compile(r"(?:\+?\d[\d\-\s().]{7,}\d)")
_URL = re.compile(r"(?:https?://)?(?:www\.)?[A-Za-z0-9.\-]+\.[A-Za-z]{2,}(?:/[^\s]*)?")
_LOCATION = re.compile(r"^[A-Za-z][A-Za-z .]*(?:,\s*[A-Za-z][A-Za-z .]*)+$")

# The name font must exceed the page median by at least this ratio to be
# confidently treated as the name via font size.
_NAME_FONT_RATIO = 1.3


class PersonalInfoExtractor:
    """Extracts name, designation and contact fields from header text."""

    # -- name / designation identification (font-based) --------------------

    def identify_name(self, lines: list[OrderedLine]) -> dict:
        """Locate the name/designation lines on page 1 by largest font.

        Returns ``{"name", "designation", "line_numbers"}``. ``line_numbers``
        is the set of line numbers to exclude from section building. Empty
        result (no font data or no confident candidate) leaves name detection
        to the header-band fallback in :meth:`build`.
        """
        result = {"name": "", "designation": "", "line_numbers": set()}
        page1 = [ln for ln in lines if ln.page == 1 and ln.font_size > 0]
        if not page1:
            return result

        fonts = sorted({ln.font_size for ln in page1})
        max_font = fonts[-1]
        median_font = fonts[len(fonts) // 2]
        if median_font <= 0 or max_font < median_font * _NAME_FONT_RATIO:
            return result  # nothing clearly larger than body -> no font-based name

        name_line = next(
            (
                ln
                for ln in page1
                if ln.font_size >= max_font * 0.98
                and ln.canonical is None
                and self._is_name_candidate(ln.text)
            ),
            None,
        )
        if name_line is None:
            return result

        result["name"] = name_line.text
        result["line_numbers"].add(name_line.line_number)

        designation = self._find_designation(lines, name_line)
        if designation is not None:
            result["designation"] = designation.text
            result["line_numbers"].add(designation.line_number)

        return result

    def _find_designation(
        self, lines: list[OrderedLine], name_line: OrderedLine
    ) -> OrderedLine | None:
        """The line immediately after the name, if it reads like a job title."""
        following = [
            ln
            for ln in lines
            if ln.line_number > name_line.line_number and ln.column == name_line.column
        ]
        # The title sits directly under the name; accept it even if it was
        # flagged as a heading (an all-caps job title can look like one), as
        # long as it is not a known section heading.
        for ln in following[:1]:
            if ln.canonical is None and self._is_designation_candidate(ln.text):
                return ln
        return None

    # -- record assembly ----------------------------------------------------

    def build(
        self,
        name_info: dict,
        header_lines: list[OrderedLine],
        extra_text: str = "",
    ) -> dict:
        """Assemble the personal_information record.

        ``header_lines`` are lines before the first heading; ``extra_text`` is
        text from a detected CONTACT/personal-information section.
        """
        header_texts = [ln.text for ln in header_lines]
        pool = header_texts + [t for t in extra_text.split("\n") if t.strip()]
        name_bits = [b for b in (name_info.get("name"), name_info.get("designation")) if b]
        raw_text = "\n".join(name_bits + pool).strip()

        info = {
            "name": name_info.get("name", ""),
            "designation": name_info.get("designation", ""),
            "phone": "",
            "email": "",
            "linkedin": "",
            "github": "",
            "portfolio": "",
            "location": "",
            "raw_text": raw_text,
        }

        self._extract_contacts(pool, info)

        # Fallback name from the header band when font-based detection found none.
        if not info["name"]:
            self._extract_name_and_title(header_texts, info)

        self._extract_location(pool, info)
        return info

    # -- contact fields -----------------------------------------------------

    def _extract_contacts(self, pool: list[str], info: dict) -> None:
        for text in pool:
            if not info["email"]:
                email = _EMAIL.search(text)
                if email:
                    info["email"] = email.group(0)

            for url in _URL.findall(text):
                u = url.lower()
                if "linkedin" in u and not info["linkedin"]:
                    info["linkedin"] = url
                elif "github" in u and not info["github"]:
                    info["github"] = url
                elif (
                    not info["portfolio"]
                    and "@" not in url
                    and "linkedin" not in u
                    and "github" not in u
                    and self._looks_like_site(u)
                ):
                    info["portfolio"] = url

            if not info["phone"] and (any(ch in text for ch in "+()") or self._digit_count(text) >= 8):
                phone = _PHONE.search(text)
                if phone and self._digit_count(phone.group(0)) >= 8:
                    info["phone"] = phone.group(0).strip()

    @staticmethod
    def _looks_like_site(url: str) -> bool:
        return any(
            url.endswith(tld) or tld + "/" in url
            for tld in (".com", ".dev", ".io", ".me", ".net", ".org")
        )

    @staticmethod
    def _digit_count(text: str) -> int:
        return sum(c.isdigit() for c in text)

    # -- name / designation (text fallback) --------------------------------

    def _extract_name_and_title(self, header_texts: list[str], info: dict) -> None:
        candidates = [t for t in header_texts if self._is_name_candidate(t)]
        if not candidates:
            return
        info["name"] = candidates[0]
        idx = header_texts.index(candidates[0])
        for text in header_texts[idx + 1:]:
            if self._is_designation_candidate(text):
                info["designation"] = text
                break

    def _is_name_candidate(self, text: str) -> bool:
        if _EMAIL.search(text) or _URL.search(text) or any(c.isdigit() for c in text):
            return False
        words = text.split()
        if not (1 <= len(words) <= 4):
            return False
        alpha = [w for w in words if any(c.isalpha() for c in w)]
        if not alpha:
            return False
        return text == text.upper() or all(w[0].isupper() for w in alpha)

    def _is_designation_candidate(self, text: str) -> bool:
        if _EMAIL.search(text) or _URL.search(text):
            return False
        words = text.split()
        return 1 <= len(words) <= 6 and self._digit_count(text) == 0

    # -- location -----------------------------------------------------------

    def _extract_location(self, pool: list[str], info: dict) -> None:
        if info["location"]:
            return
        for text in pool:
            if _EMAIL.search(text) or "http" in text.lower():
                continue
            if _LOCATION.match(text) and len(text.split()) <= 6:
                info["location"] = text
                return
