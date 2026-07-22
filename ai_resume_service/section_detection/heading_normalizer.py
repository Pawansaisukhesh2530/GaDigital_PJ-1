"""
heading_normalizer.py

Stage 4 of the pipeline.

Maps a heading's raw text to a canonical section key using the mapping table
in :mod:`section_detection.section_keywords`. This is the *only* place that
knows how a heading string becomes a canonical key, so both the heading
detector (which uses the keyword signal as one heuristic) and the section
builder rely on it — the mapping is never duplicated.
"""

from __future__ import annotations

import re

from section_detection.section_keywords import build_keyword_lookup

# Reduce a heading to a lookup key: keep letters/digits only, lowercase.
# "Work Experience:" and "WORK  EXPERIENCE" both reduce to "workexperience".
_NON_ALNUM = re.compile(r"[^a-z0-9]")


class HeadingNormalizer:
    """Resolves raw heading text to a canonical section key."""

    def __init__(self) -> None:
        self._lookup: dict[str, str] = build_keyword_lookup()

    @staticmethod
    def reduce(text: str) -> str:
        """Reduce raw heading text to its alphanumeric, lowercase lookup form."""
        return _NON_ALNUM.sub("", text.lower())

    def map(self, text: str) -> str | None:
        """Return the canonical key for ``text`` or ``None`` if unrecognized."""
        return self._lookup.get(self.reduce(text))
