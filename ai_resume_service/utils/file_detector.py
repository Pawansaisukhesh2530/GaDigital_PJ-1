"""
file_detector.py

Reusable file-type detection for resume files.

Detection is based primarily on file extension, with a lightweight magic-byte
sniff as a sanity check (guards against a renamed/mismatched extension without
requiring a heavyweight dependency like ``python-magic``). This keeps
Milestone 1 dependency-light while still being trustworthy enough to route
files to the correct extractor.
"""

from __future__ import annotations

from dataclasses import dataclass
from enum import Enum
from pathlib import Path


class FileType(str, Enum):
    """Resume file types the service can recognize."""

    PDF = "PDF"
    DOCX = "DOCX"
    DOC = "DOC"          # recognized, not yet extractable (future milestone)
    PNG = "PNG"          # recognized, reserved for future OCR milestone
    JPEG = "JPEG"         # recognized, reserved for future OCR milestone
    UNKNOWN = "UNKNOWN"


# Magic byte signatures used as a sanity check against the extension.
_SIGNATURES: dict[bytes, FileType] = {
    b"%PDF": FileType.PDF,
    b"PK\x03\x04": FileType.DOCX,  # DOCX is a zip container
    b"\xff\xd8\xff": FileType.JPEG,
    b"\x89PNG\r\n\x1a\n": FileType.PNG,
    b"\xd0\xcf\x11\xe0": FileType.DOC,  # legacy OLE2 binary format
}

_EXTENSION_MAP: dict[str, FileType] = {
    ".pdf": FileType.PDF,
    ".docx": FileType.DOCX,
    ".doc": FileType.DOC,
    ".png": FileType.PNG,
    ".jpg": FileType.JPEG,
    ".jpeg": FileType.JPEG,
}


@dataclass(frozen=True)
class DetectionResult:
    """Outcome of file-type detection."""

    file_type: FileType
    extension: str
    is_supported: bool
    """True only for types Milestone 1 can actually extract text from."""


def _sniff_signature(path: Path) -> FileType | None:
    """Best-effort peek at the first bytes of the file to confirm its type.

    Returns None if the file cannot be read or no known signature matches.
    """
    try:
        with path.open("rb") as f:
            header = f.read(8)
    except OSError:
        return None

    for signature, file_type in _SIGNATURES.items():
        if header.startswith(signature):
            return file_type
    return None


def detect_file_type(file_path: str | Path) -> DetectionResult:
    """Detect the type of a resume file.

    Uses the file extension as the primary signal and cross-checks the first
    bytes of the file when possible. Never raises — unrecognized or unreadable
    files simply resolve to ``FileType.UNKNOWN``.
    """
    path = Path(file_path)
    extension = path.suffix.lower()

    extension_type = _EXTENSION_MAP.get(extension, FileType.UNKNOWN)
    signature_type = _sniff_signature(path) if path.exists() else None

    # Prefer the signature-based result when available and it disagrees with
    # the extension (helps catch a mislabeled file); otherwise trust the
    # extension.
    resolved_type = signature_type or extension_type

    supported = resolved_type in (FileType.PDF, FileType.DOCX)

    return DetectionResult(
        file_type=resolved_type,
        extension=extension,
        is_supported=supported,
    )
