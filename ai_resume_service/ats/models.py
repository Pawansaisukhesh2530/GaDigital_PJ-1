"""
models.py

Typed data structures shared across the ATS layout pipeline. Each stage
consumes and enriches these objects, which keeps stages decoupled and makes
every intermediate state serializable for the debug artifacts.
"""

from __future__ import annotations

from dataclasses import asdict, dataclass, field

# Column sentinel for full-width header/footer bands (name, contact strip that
# spans the whole page). These are read before any column content.
HEADER_BAND_COLUMN = -1


@dataclass
class TextLine:
    """A single visual line of text within a block, with its font metadata."""

    text: str
    font_size: float = 0.0
    is_bold: bool = False


@dataclass
class Block:
    """A text block extracted from the document with its geometry (Stage 1).

    Coordinates use the PDF origin (top-left), in points. ``column`` and
    ``reading_priority`` are filled in by Stages 2 and 3 respectively.
    """

    page: int
    x0: float
    y0: float
    x1: float
    y1: float
    lines: list[TextLine] = field(default_factory=list)
    band: int = 0
    column: int = 0
    reading_priority: int = 0

    @property
    def width(self) -> float:
        return self.x1 - self.x0

    @property
    def height(self) -> float:
        return self.y1 - self.y0

    @property
    def x_center(self) -> float:
        return (self.x0 + self.x1) / 2.0

    @property
    def text(self) -> str:
        return "\n".join(line.text for line in self.lines).strip()

    @property
    def font_size(self) -> float:
        """Representative font size (largest line font in the block)."""
        return max((line.font_size for line in self.lines), default=0.0)

    @property
    def is_bold(self) -> bool:
        return any(line.is_bold for line in self.lines)

    def to_layout_dict(self) -> dict:
        """Serializable record for ``document_layout.json``."""
        return {
            "page": self.page,
            "text": self.text,
            "bbox": [round(self.x0, 1), round(self.y0, 1), round(self.x1, 1), round(self.y1, 1)],
            "x": round(self.x0, 1),
            "y": round(self.y0, 1),
            "width": round(self.width, 1),
            "height": round(self.height, 1),
            "font_size": round(self.font_size, 1),
            "is_bold": self.is_bold,
            "band": self.band,
            "column": self.column,
            "reading_priority": self.reading_priority,
        }


@dataclass
class OrderedLine:
    """A line in reconstructed reading order, annotated for heading detection."""

    text: str
    page: int
    column: int
    line_number: int
    font_size: float = 0.0
    is_bold: bool = False
    is_block_start: bool = False
    is_heading: bool = False
    heading_score: float = 0.0
    canonical: str | None = None

    def to_debug_dict(self) -> dict:
        return {
            "line": self.line_number,
            "page": self.page,
            "column": self.column,
            "text": self.text,
            "font_size": round(self.font_size, 1),
            "is_bold": self.is_bold,
            "is_heading": self.is_heading,
            "heading_score": round(self.heading_score, 3),
            "mapped_section": self.canonical,
        }


@dataclass
class PageLayout:
    """Column-detection outcome for one page (Stage 2)."""

    page: int
    column_count: int
    gutters: list[tuple[float, float]] = field(default_factory=list)
    content_width: float = 0.0
    column_bounds: list[tuple[float, float]] = field(default_factory=list)
    confidence: float = 0.0

    def to_debug_dict(self) -> dict:
        data = asdict(self)
        data["gutters"] = [[round(a, 1), round(b, 1)] for a, b in self.gutters]
        data["column_bounds"] = [[round(a, 1), round(b, 1)] for a, b in self.column_bounds]
        data["content_width"] = round(self.content_width, 1)
        data["confidence"] = round(self.confidence, 2)
        return data


@dataclass
class DetectedSection:
    """A section built from a heading boundary (Stages 6 & 8)."""

    canonical: str
    heading: str
    page: int
    column: int
    start_line: int
    end_line: int
    confidence: float
    raw_text: str
    warnings: list[str] = field(default_factory=list)

    def to_debug_dict(self) -> dict:
        return {
            "canonical": self.canonical,
            "heading": self.heading,
            "page": self.page,
            "column": self.column,
            "start_line": self.start_line,
            "end_line": self.end_line,
            "confidence": round(self.confidence, 2),
            "warnings": self.warnings,
        }
