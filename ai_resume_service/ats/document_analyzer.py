"""
document_analyzer.py

Stage 1: turn a document into positioned text blocks.

For PDFs we use PyMuPDF's ``get_text("dict")`` to recover each block's
bounding box plus per-line font size and bold flag — the geometry the rest of
the pipeline needs to reconstruct reading order. For non-PDF inputs (DOCX) no
coordinates exist, so we fall back to wrapping the already-extracted plain
text into single-column synthetic blocks; those documents are linear anyway,
so reading order is already correct.

This stage never raises: on any failure it returns whatever blocks it managed
to collect (possibly the text fallback) so the pipeline degrades gracefully.
"""

from __future__ import annotations

import unicodedata
from pathlib import Path

import fitz  # PyMuPDF

from ats.models import Block, TextLine
from utils.file_detector import FileType

# PyMuPDF span flag bit for bold text.
_BOLD_FLAG = 1 << 4


class DocumentAnalyzer:
    """Extracts positioned text blocks (Stage 1) from a resume file."""

    def analyze(
        self,
        file_path: str | Path,
        file_type: FileType,
        fallback_text: str = "",
    ) -> tuple[list[Block], list[float]]:
        """Return (blocks, page_widths).

        ``page_widths`` is indexed by page-1 and used by column detection.
        For non-PDF inputs a single synthetic page width of 1.0 is returned.
        """
        path = Path(file_path)
        if file_type == FileType.PDF and path.exists():
            try:
                return self._analyze_pdf(path)
            except Exception:  # noqa: BLE001 - never crash; fall back to text
                pass
        return self._analyze_text(fallback_text)

    # -- PDF ----------------------------------------------------------------

    def _analyze_pdf(self, path: Path) -> tuple[list[Block], list[float]]:
        blocks: list[Block] = []
        page_widths: list[float] = []

        with fitz.open(path) as doc:
            for page_index, page in enumerate(doc, start=1):
                page_widths.append(float(page.rect.width))
                data = page.get_text("dict")
                for raw_block in data.get("blocks", []):
                    if raw_block.get("type", 0) != 0:
                        continue  # skip image blocks
                    # Emit one Block per visual line (with its own bbox) rather
                    # than per PyMuPDF block: PyMuPDF sometimes groups text from
                    # adjacent columns into one block, which would defeat column
                    # detection. Line-level geometry keeps columns separable.
                    for raw_line in raw_block.get("lines", []):
                        block = self._build_line_block(raw_line, page_index)
                        if block is not None:
                            blocks.append(block)

        if not blocks:
            # Text-based PDF with no dict blocks (rare) -> plain-text fallback.
            return self._analyze_text(self._flat_text(path))

        return blocks, page_widths

    def _build_line_block(self, raw_line: dict, page_index: int) -> Block | None:
        spans = raw_line.get("spans", [])
        text = self._normalize("".join(span.get("text", "") for span in spans))
        if not text:
            return None

        font_size, is_bold = self._line_font(spans)
        x0, y0, x1, y1 = raw_line.get("bbox", (0.0, 0.0, 0.0, 0.0))
        return Block(
            page=page_index,
            x0=x0,
            y0=y0,
            x1=x1,
            y1=y1,
            lines=[TextLine(text=text, font_size=font_size, is_bold=is_bold)],
        )

    @staticmethod
    def _line_font(spans: list[dict]) -> tuple[float, bool]:
        """Pick the representative span (most characters) for font metadata."""
        if not spans:
            return 0.0, False
        dominant = max(spans, key=lambda s: len(s.get("text", "")))
        size = float(dominant.get("size", 0.0))
        flags = int(dominant.get("flags", 0))
        font_name = str(dominant.get("font", "")).lower()
        is_bold = bool(flags & _BOLD_FLAG) or "bold" in font_name or "black" in font_name
        return size, is_bold

    @staticmethod
    def _flat_text(path: Path) -> str:
        try:
            with fitz.open(path) as doc:
                return "\n".join(page.get_text() for page in doc)
        except Exception:  # noqa: BLE001
            return ""

    # -- Non-PDF fallback ---------------------------------------------------

    def _analyze_text(self, text: str) -> tuple[list[Block], list[float]]:
        """Wrap linear text into single-column synthetic blocks (DOCX etc.)."""
        blocks: list[Block] = []
        y = 0.0
        for raw_line in (text or "").split("\n"):
            line = self._normalize(raw_line)
            if not line:
                y += 1.0
                continue
            blocks.append(
                Block(
                    page=1,
                    x0=0.0,
                    y0=y,
                    x1=1.0,
                    y1=y + 1.0,
                    lines=[TextLine(text=line)],
                )
            )
            y += 1.0
        return blocks, [1.0]

    # -- shared -------------------------------------------------------------

    @staticmethod
    def _normalize(text: str) -> str:
        return unicodedata.normalize("NFKC", text).replace("\t", " ").strip()
