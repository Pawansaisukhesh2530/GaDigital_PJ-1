"""
ats_parser.py

Orchestrates the full layout-aware ATS pipeline (Stages 1-9) and writes the
per-stage debug artifacts (Stage 10).

    analyze -> detect columns -> reconstruct reading order -> detect headings
    -> build sections -> extract personal info -> validate -> assemble JSON

The public entry point is :meth:`AtsResumeParser.parse`, which never raises:
any unexpected failure degrades to an ``others``-only result so the CLI keeps
running. The assembled structure matches the documented ``sections.json``
schema and is exactly what a later LLM milestone can consume.
"""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path

from ats.column_detector import ColumnDetector
from ats.document_analyzer import DocumentAnalyzer
from ats.heading_detector import LayoutHeadingDetector
from ats.models import Block, DetectedSection, OrderedLine, PageLayout
from ats.personal_info import PersonalInfoExtractor
from ats.reading_order import ReadingOrderBuilder
from ats.section_builder import SectionBuilder
from ats.section_validator import SectionValidator
from ats.settings import COLUMN_SETTINGS, PARSER_VERSION
from section_detection.section_keywords import CANONICAL_SECTION_ORDER
from utils.file_detector import FileType

# Canonical sections that get a dedicated top-level key in the output schema.
# ``personal_information`` and ``others`` are assembled separately.
_SECTION_KEYS = tuple(
    k for k in CANONICAL_SECTION_ORDER if k not in ("personal_information", "others")
)


@dataclass
class AtsParseResult:
    """Everything produced by a parse, plus the debug intermediates."""

    document: dict
    blocks: list[Block] = field(default_factory=list)
    ordered_blocks: list[Block] = field(default_factory=list)
    ordered_lines: list[OrderedLine] = field(default_factory=list)
    page_layouts: list[PageLayout] = field(default_factory=list)
    sections: list[DetectedSection] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)


class AtsResumeParser:
    """End-to-end layout-aware resume parser."""

    def __init__(self) -> None:
        self._analyzer = DocumentAnalyzer()
        self._columns = ColumnDetector()
        self._reading_order = ReadingOrderBuilder()
        self._headings = LayoutHeadingDetector()
        self._sections = SectionBuilder()
        self._personal = PersonalInfoExtractor()
        self._validator = SectionValidator()

    def parse(
        self,
        file_path: str | Path,
        file_type: FileType,
        fallback_text: str = "",
    ) -> AtsParseResult:
        """Run the pipeline. Never raises."""
        try:
            blocks, page_widths = self._analyzer.analyze(file_path, file_type, fallback_text)
            if not blocks:
                return self._empty_result("No text blocks could be extracted.")

            layouts = self._columns.detect(blocks, page_widths)            # Stage 2
            ordered_blocks, lines = self._reading_order.build(blocks)      # Stage 3
            self._headings.annotate(lines)                                 # Stage 4 + 5

            # Stage 7a: claim the name/title (largest font on page 1) before
            # section building so it never opens a spurious section.
            name_info = self._personal.identify_name(lines)
            section_lines = [
                ln for ln in lines if ln.line_number not in name_info["line_numbers"]
            ]

            sections, header_band = self._sections.build(section_lines)    # Stage 6
            personal = self._personal.build(                               # Stage 7b
                name_info, header_band, self._personal_section_text(sections)
            )
            warnings = self._validator.validate(sections)                  # Stage 8

            document = self._assemble(                                     # Stage 9
                sections, personal, layouts, page_widths, warnings
            )

            return AtsParseResult(
                document=document,
                blocks=blocks,
                ordered_blocks=ordered_blocks,
                ordered_lines=lines,
                page_layouts=layouts,
                sections=sections,
                warnings=warnings,
            )
        except Exception as exc:  # noqa: BLE001 - never crash the CLI
            return self._empty_result(f"ATS parsing failed: {exc}", fallback_text)

    # -- Stage 9: assemble output ------------------------------------------

    def _assemble(
        self,
        sections: list[DetectedSection],
        personal: dict,
        layouts: list[PageLayout],
        page_widths: list[float],
        warnings: list[str],
    ) -> dict:
        grouped = self._group_sections(sections)
        layout_type, layout_conf = self._classify_layout(layouts)

        document: dict = {
            "metadata": {
                "parser_version": PARSER_VERSION,
                "page_count": len(page_widths),
                "layout": layout_type,
                "confidence": self._overall_confidence(grouped, personal, layout_conf),
            },
            "personal_information": personal,
        }

        for key in _SECTION_KEYS:
            document[key] = grouped.get(key, self._empty_section())

        document["others"] = grouped.get("others", self._empty_section())
        document["warnings"] = warnings
        return document

    def _group_sections(self, sections: list[DetectedSection]) -> dict[str, dict]:
        """Merge detected sections by canonical key into schema objects."""
        grouped: dict[str, dict] = {}
        for section in sections:
            body = section.raw_text.strip()
            key = section.canonical
            if key not in grouped:
                grouped[key] = {
                    "heading": section.heading,
                    "page": section.page,
                    "confidence": round(section.confidence, 2),
                    "raw_text": body,
                }
            else:
                # Duplicate canonical: append text, keep the lower confidence.
                existing = grouped[key]
                existing["raw_text"] = f"{existing['raw_text']}\n{body}".strip()
                existing["confidence"] = round(min(existing["confidence"], section.confidence), 2)
        return grouped

    @staticmethod
    def _empty_section() -> dict:
        return {"heading": "", "page": 0, "confidence": 0.0, "raw_text": ""}

    def _classify_layout(self, layouts: list[PageLayout]) -> tuple[str, float]:
        if not layouts:
            return "unknown", 0.5

        max_cols = max(layout.column_count for layout in layouts)
        confidence = round(sum(l.confidence for l in layouts) / len(layouts), 2)

        if max_cols <= 1:
            return "single_column", confidence
        if max_cols == 2:
            return self._two_column_kind(layouts), confidence
        return "multi_column", confidence

    def _two_column_kind(self, layouts: list[PageLayout]) -> str:
        """Distinguish a balanced two-column layout from a sidebar layout."""
        for layout in layouts:
            if len(layout.column_bounds) != 2:
                continue
            widths = [right - left for left, right in layout.column_bounds]
            narrow, wide = min(widths), max(widths)
            if wide > 0 and narrow / wide <= COLUMN_SETTINGS["sidebar_width_ratio"]:
                return "sidebar"
        return "two_column"

    def _overall_confidence(self, grouped: dict, personal: dict, layout_conf: float) -> float:
        section_confs = [
            obj["confidence"]
            for key, obj in grouped.items()
            if key != "others" and obj["raw_text"]
        ]
        section_score = sum(section_confs) / len(section_confs) if section_confs else 0.4
        personal_score = self._personal_completeness(personal)
        overall = 0.5 * section_score + 0.3 * layout_conf + 0.2 * personal_score
        return round(min(0.99, overall), 2)

    @staticmethod
    def _personal_completeness(personal: dict) -> float:
        fields = ("name", "email", "phone")
        present = sum(1 for f in fields if personal.get(f))
        return present / len(fields)

    @staticmethod
    def _personal_section_text(sections: list[DetectedSection]) -> str:
        return "\n".join(
            s.raw_text for s in sections if s.canonical == "personal_information"
        )

    # -- fallbacks ----------------------------------------------------------

    def _empty_result(self, message: str, fallback_text: str = "") -> AtsParseResult:
        document = {
            "metadata": {
                "parser_version": PARSER_VERSION,
                "page_count": 0,
                "layout": "unknown",
                "confidence": 0.0,
            },
            "personal_information": self._personal.build(
                {"name": "", "designation": "", "line_numbers": set()}, [], fallback_text
            ),
        }
        for key in _SECTION_KEYS:
            document[key] = self._empty_section()
        document["others"] = {
            "heading": "",
            "page": 0,
            "confidence": 0.0,
            "raw_text": fallback_text.strip(),
        }
        document["warnings"] = [message]
        return AtsParseResult(document=document, warnings=[message])

    # -- Stage 10: debug artifacts -----------------------------------------

    def write_debug_artifacts(self, result: AtsParseResult, debug_dir: Path) -> None:
        """Write all five debug files. Best-effort; swallows I/O errors."""
        try:
            debug_dir.mkdir(parents=True, exist_ok=True)
            self._dump_json(
                debug_dir / "document_layout.json",
                [b.to_layout_dict() for b in result.blocks],
            )
            self._dump_json(
                debug_dir / "ordered_document.json",
                {
                    "page_layouts": [l.to_debug_dict() for l in result.page_layouts],
                    "blocks": [b.to_layout_dict() for b in result.ordered_blocks],
                },
            )
            self._dump_json(
                debug_dir / "heading_detection.json",
                [ln.to_debug_dict() for ln in result.ordered_lines],
            )
            self._dump_json(
                debug_dir / "section_boundaries.json",
                [s.to_debug_dict() for s in result.sections],
            )
            self._write_normalized_lines(
                debug_dir / "normalized_lines.txt", result.ordered_lines
            )
        except OSError:
            pass

    @staticmethod
    def _dump_json(path: Path, payload) -> None:
        path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")

    @staticmethod
    def _write_normalized_lines(path: Path, lines: list[OrderedLine]) -> None:
        rows = [
            f"{ln.line_number:>4} | p{ln.page} c{ln.column} | {ln.text}" for ln in lines
        ]
        path.write_text("\n".join(rows), encoding="utf-8")
