"""
api/utils/file_security.py

Upload validation and safe internal filename generation (Parts 8, 9).

Files are validated by extension AND magic-byte signature; the candidate's
filename is never used as a filesystem path. A safe internal name is generated
inside the request workspace.
"""

from __future__ import annotations

from pathlib import Path

from api.errors import ErrorCode, ServiceError

# Extensions the API accepts (PDF, DOC, DOCX). DOC is accepted at the boundary
# but the frozen extractor may reject legacy .doc downstream with a clean error.
_ALLOWED_EXTENSIONS = {".pdf", ".doc", ".docx"}

# Magic-byte signatures for accepted types.
_PDF_SIG = b"%PDF"
_ZIP_SIG = b"PK\x03\x04"          # DOCX is a zip container
_OLE_SIG = b"\xd0\xcf\x11\xe0"    # legacy .doc (OLE2)

_UNSUPPORTED_MSG = "Only PDF, DOC and DOCX files are supported."


def safe_extension(filename: str | None) -> str:
    """Return a validated lowercase extension, or raise UNSUPPORTED_FILE_TYPE."""
    ext = Path(filename or "").suffix.lower()
    if ext not in _ALLOWED_EXTENSIONS:
        raise ServiceError(
            ErrorCode.UNSUPPORTED_FILE_TYPE,
            _UNSUPPORTED_MSG,
            internal=f"rejected extension={ext!r} for filename={filename!r}",
        )
    return ext


def validate_size(num_bytes: int, max_bytes: int) -> None:
    """Reject empty or oversized uploads with controlled errors."""
    if num_bytes <= 0:
        raise ServiceError(ErrorCode.EMPTY_UPLOAD, "The uploaded file is empty.")
    if num_bytes > max_bytes:
        limit_mb = max_bytes // (1024 * 1024)
        raise ServiceError(
            ErrorCode.FILE_TOO_LARGE,
            f"File exceeds the maximum allowed size of {limit_mb} MB.",
            internal=f"size={num_bytes} exceeds max_bytes={max_bytes}",
        )


def verify_signature(content: bytes, ext: str) -> None:
    """Cross-check magic bytes against the claimed extension (Part 8)."""
    header = content[:8]
    if ext == ".pdf":
        ok = header.startswith(_PDF_SIG)
    elif ext == ".docx":
        ok = header.startswith(_ZIP_SIG)
    elif ext == ".doc":
        ok = header.startswith(_OLE_SIG)
    else:  # pragma: no cover - guarded earlier by safe_extension
        ok = False

    if not ok:
        raise ServiceError(
            ErrorCode.INVALID_FILE,
            "The file content does not match its extension or is corrupt.",
            internal=f"signature mismatch for ext={ext}, header={header!r}",
        )


def internal_filename(ext: str) -> str:
    """Generate a safe, fixed internal filename (never candidate-provided)."""
    return f"original_resume{ext}"
