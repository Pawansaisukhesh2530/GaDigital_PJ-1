"""
heading_detector.py

Stage 4: classify ordered lines as headings using multiple signals.

Text-shape signals (word count, casing, punctuation, keyword-dictionary match)
are combined with layout signals only available now that geometry is known:
relative font size (vs. the page median), bold weight, and whether the line
starts a block (i.e. is preceded by whitespace). Keyword mapping is delegated
to the shared :class:`HeadingNormalizer` so the canonical vocabulary is never
duplicated. Weights live in settings/config, not inline.
"""

from __future__ import annotations

import re
import statistics

from ats.models import OrderedLine
from ats.settings import LAYOUT_HEADING_SETTINGS
from section_detection.heading_normalizer import HeadingNormalizer
from section_detection.section_keywords import HEADING_HEURISTICS

_ENDS_WITH_SENTENCE = re.compile(r"[.!?]$")
_ENDS_WITH_PUNCT = re.compile(r"[.,;!?]$")
_LOOKS_LIKE_PROSE = re.compile(r"[a-z]\. [A-Z]")


class LayoutHeadingDetector:
    """Scores and flags heading lines using text + font + position signals."""

    def __init__(self, normalizer: HeadingNormalizer | None = None) -> None:
        self._normalizer = normalizer or HeadingNormalizer()
        self._text_cfg = HEADING_HEURISTICS
        self._layout_cfg = LAYOUT_HEADING_SETTINGS

    def annotate(self, lines: list[OrderedLine]) -> list[OrderedLine]:
        """Set ``is_heading``/``heading_score``/``canonical`` on each line."""
        median_font = self._median_font(lines)
        threshold = self._layout_cfg["score_threshold"]

        # First pass: score every line so we can learn the section-heading font
        # tier from the self-identifying keyword headings.
        scored = [(line, *self._score(line, median_font)) for line in lines]
        heading_tier = self._heading_font_tier(
            [(ln, s, c) for ln, s, c in scored if s >= threshold], median_font
        )

        for line, score, canonical in scored:
            line.heading_score = score
            line.canonical = canonical
            line.is_heading = score >= threshold and self._passes_font_gate(
                line, canonical, median_font, heading_tier
            )
        return lines

    @staticmethod
    def _heading_font_tier(
        candidate_headings: list[tuple[OrderedLine, float, str | None]],
        median_font: float,
    ) -> float:
        """Learn the font size real section headings use.

        Keyword-matched headings are trustworthy anchors, so their median font
        size defines the "heading tier". Content that is merely emphasized
        (e.g. 12pt skills over 10pt body, while headings are 14pt) then falls
        below the tier and is correctly rejected. Returns 0 when there is no
        reliable anchor (the caller falls back to a simple ratio test).
        """
        fonts = [
            ln.font_size
            for ln, _score, canonical in candidate_headings
            if canonical is not None and ln.font_size > 0
        ]
        if not fonts:
            return 0.0
        fonts.sort()
        return fonts[len(fonts) // 2]  # median of keyword-heading fonts

    def _passes_font_gate(
        self,
        line: OrderedLine,
        canonical: str | None,
        median_font: float,
        heading_tier: float,
    ) -> bool:
        """Gate out non-keyword headings that are not visually distinguished.

        A line matching the keyword dictionary is always allowed (headings are
        self-identifying). A line that is NOT a known heading must reach the
        section-heading font tier: institution names, dates, job titles and
        emphasized skill entries are frequently bold or slightly larger than
        body text but still smaller than true section headings — they are
        content. When font data is unavailable (median 0, e.g. DOCX/text
        fallback), the gate is a no-op and the text-shape score alone decides.
        """
        if canonical is not None:
            return True
        if median_font <= 0 or line.font_size <= 0:
            return True
        if heading_tier > 0:
            return line.font_size >= heading_tier * 0.9
        return line.font_size >= median_font * self._layout_cfg["font_large_ratio"]

    @staticmethod
    def _median_font(lines: list[OrderedLine]) -> float:
        sizes = [ln.font_size for ln in lines if ln.font_size > 0]
        return statistics.median(sizes) if sizes else 0.0

    def _score(self, line: OrderedLine, median_font: float) -> tuple[float, str | None]:
        text = line.text
        words = text.split()
        word_count = len(words)

        if word_count == 0 or word_count > self._text_cfg["max_heading_words"]:
            return 0.0, None

        canonical = self._normalizer.map(text)
        score = 0.0

        # --- text-shape signals -------------------------------------------
        if canonical is not None:
            score += self._text_cfg["weight_keyword_match"]
        score += self._text_cfg["weight_few_words"] if word_count <= 3 else self._text_cfg["weight_some_words"]

        letters = [c for c in text if c.isalpha()]
        if letters and text == text.upper():
            score += self._text_cfg["weight_all_caps"]
        elif self._is_title_case(words):
            score += self._text_cfg["weight_title_case"]

        if text.endswith(":"):
            score += self._text_cfg["weight_trailing_colon"]
        elif not _ENDS_WITH_PUNCT.search(text):
            score += self._text_cfg["weight_no_end_punct"]

        if _ENDS_WITH_SENTENCE.search(text):
            score -= self._text_cfg["penalty_sentence_end"]
        if _LOOKS_LIKE_PROSE.search(text) or text.count(",") >= 2:
            score -= self._text_cfg["penalty_prose"]

        if len(text) <= self._text_cfg["max_heading_chars"]:
            score += self._text_cfg["weight_short_chars"]
        else:
            score -= self._text_cfg["penalty_long"]

        # --- layout signals -----------------------------------------------
        if median_font > 0 and line.font_size > 0:
            ratio = line.font_size / median_font
            if ratio >= self._layout_cfg["font_larger_ratio"]:
                score += self._layout_cfg["weight_font_larger"]
            elif ratio >= self._layout_cfg["font_large_ratio"]:
                score += self._layout_cfg["weight_font_large"]

        if line.is_bold:
            score += self._layout_cfg["weight_bold"]
        if line.is_block_start:
            score += self._layout_cfg["weight_block_start"]

        return self._clamp(score), canonical

    @staticmethod
    def _is_title_case(words: list[str]) -> bool:
        alpha_words = [w for w in words if any(c.isalpha() for c in w)]
        if not alpha_words:
            return False
        return all(w[0].isupper() for w in alpha_words)

    @staticmethod
    def _clamp(value: float) -> float:
        return max(0.0, min(1.0, value))
