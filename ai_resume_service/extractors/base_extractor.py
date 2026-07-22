"""
base_extractor.py

Abstract base class every extractor implements.

This is the extension point future milestones rely on: an OCR-based extractor
(for scanned PDFs / images) can be added later simply by subclassing
``BaseExtractor`` and registering it in ``ExtractorFactory`` — no changes
required to existing PDF/DOCX extractors or to ``main.py``.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from pathlib import Path


@dataclass
class ExtractionResult:
    """Outcome of a text extraction attempt.

    Attributes:
        success: Whether readable text was produced.
        text: The extracted text (empty string if none).
        page_count: Number of pages/sections processed, when applicable.
        message: Human-readable status or error message.
    """

    success: bool
    text: str = ""
    page_count: int = 0
    message: str = ""
    warnings: list[str] = field(default_factory=list)


class BaseExtractor(ABC):
    """Common interface for all resume text extractors."""

    def __init__(self, file_path: str | Path) -> None:
        self.file_path = Path(file_path)

    @abstractmethod
    def extract(self) -> ExtractionResult:
        """Extract readable text from ``self.file_path``.

        Implementations must never raise for expected failure modes
        (corrupted file, empty document, permission errors) — they should
        return an ``ExtractionResult`` with ``success=False`` and a clear
        ``message`` instead.
        """
        raise NotImplementedError
