"""
extractor_factory.py

Given a file path, detects its type and returns the correct extractor
instance. This is the single place that maps "kind of file" to
"extractor implementation" — adding OCR support later means adding one
entry to ``_EXTRACTOR_MAP`` (or raising ``UnsupportedFileTypeError`` with a
more specific message), without touching any existing extractor or caller.
"""

from __future__ import annotations

from pathlib import Path

from extractors.base_extractor import BaseExtractor
from extractors.docx_extractor import DOCXExtractor
from extractors.pdf_extractor import PDFExtractor
from utils.file_detector import DetectionResult, FileType, detect_file_type


class UnsupportedFileTypeError(Exception):
    """Raised when no extractor is available for the detected file type."""


# Maps a detected FileType to the extractor class that handles it.
# Future milestone: add FileType.PNG / FileType.JPEG -> OCRExtractor here.
_EXTRACTOR_MAP: dict[FileType, type[BaseExtractor]] = {
    FileType.PDF: PDFExtractor,
    FileType.DOCX: DOCXExtractor,
}

# Friendly guidance for types we recognize but cannot extract yet.
_FUTURE_TYPE_MESSAGES: dict[FileType, str] = {
    FileType.DOC: "Legacy .doc files are not yet supported. Please convert to .docx or .pdf.",
    FileType.PNG: "Image files require OCR, which will be added in a future milestone.",
    FileType.JPEG: "Image files require OCR, which will be added in a future milestone.",
}


class ExtractorFactory:
    """Creates the appropriate extractor instance for a given file."""

    @staticmethod
    def detect(file_path: str | Path) -> DetectionResult:
        """Expose file-type detection so callers can log it before extracting."""
        return detect_file_type(file_path)

    @staticmethod
    def get_extractor(file_path: str | Path) -> BaseExtractor:
        """Return an extractor instance capable of handling ``file_path``.

        Raises:
            UnsupportedFileTypeError: if the file type is unknown, or is a
                recognized-but-not-yet-supported type (e.g. legacy .doc,
                image formats reserved for a future OCR milestone).
        """
        detection = detect_file_type(file_path)

        extractor_cls = _EXTRACTOR_MAP.get(detection.file_type)
        if extractor_cls is not None:
            return extractor_cls(file_path)

        if detection.file_type in _FUTURE_TYPE_MESSAGES:
            raise UnsupportedFileTypeError(_FUTURE_TYPE_MESSAGES[detection.file_type])

        raise UnsupportedFileTypeError(
            f"Unsupported or unrecognized file type: '{detection.extension or 'unknown'}'."
        )
