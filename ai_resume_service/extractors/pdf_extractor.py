"""
pdf_extractor.py

Extracts readable text from PDF files.

Strategy:
    1. Try PyMuPDF (``fitz``) first — it is fast and handles most text-based
       PDFs well.
    2. If PyMuPDF yields no text at all, retry with ``pdfplumber`` as a
       second opinion (different underlying parser; occasionally succeeds
       where PyMuPDF does not on unusual PDF encodings).
    3. If neither produces any text, the PDF is very likely a scanned /
       image-only document. Milestone 1 does not perform OCR — we return a
       clear, actionable message instead of pretending extraction failed.

No OCR is performed here. Corrupted files, permission errors, and encrypted
PDFs are all handled gracefully and never raise.
"""

from __future__ import annotations

from pathlib import Path

import fitz  # PyMuPDF
import pdfplumber

from extractors.base_extractor import BaseExtractor, ExtractionResult

_OCR_HINT = (
    "No extractable text found. This document may be a scanned image or "
    "contain only images. OCR-based extraction will be added in a future "
    "milestone."
)


class PDFExtractor(BaseExtractor):
    """Extracts text from PDF files using PyMuPDF with a pdfplumber fallback."""

    def extract(self) -> ExtractionResult:
        if not self.file_path.exists():
            return ExtractionResult(success=False, message=f"File not found: {self.file_path}")

        try:
            text, page_count = self._extract_with_pymupdf()
        except Exception as exc:  # noqa: BLE001 - PDFs can fail in many ways
            return ExtractionResult(
                success=False,
                message=f"Failed to open PDF (it may be corrupted or encrypted): {exc}",
            )

        if text.strip():
            return ExtractionResult(success=True, text=text, page_count=page_count)

        # No text from PyMuPDF — try pdfplumber as a fallback before
        # concluding this is an OCR case.
        try:
            fallback_text, fallback_pages = self._extract_with_pdfplumber()
        except Exception:  # noqa: BLE001 - fallback is best-effort only
            fallback_text, fallback_pages = "", page_count

        if fallback_text.strip():
            return ExtractionResult(success=True, text=fallback_text, page_count=fallback_pages)

        return ExtractionResult(
            success=False,
            page_count=page_count,
            message=_OCR_HINT,
        )

    def _extract_with_pymupdf(self) -> tuple[str, int]:
        """Extract text page-by-page using PyMuPDF."""
        page_texts: list[str] = []
        with fitz.open(self.file_path) as doc:
            page_count = doc.page_count
            for page in doc:
                page_text = page.get_text().strip()
                if page_text:
                    page_texts.append(page_text)
        return "\n\n".join(page_texts), page_count

    def _extract_with_pdfplumber(self) -> tuple[str, int]:
        """Extract text page-by-page using pdfplumber (fallback engine)."""
        page_texts: list[str] = []
        with pdfplumber.open(self.file_path) as pdf:
            page_count = len(pdf.pages)
            for page in pdf.pages:
                page_text = (page.extract_text() or "").strip()
                if page_text:
                    page_texts.append(page_text)
        return "\n\n".join(page_texts), page_count
