"""
api/errors.py

Central error taxonomy for the API. ``ServiceError`` carries a client-safe code
and message plus an internal detail that is logged server-side only — client
responses NEVER include stack traces, absolute paths, or Ollama internals
(Part 4).
"""

from __future__ import annotations


class ErrorCode:
    EMPTY_UPLOAD = "EMPTY_UPLOAD"
    UNSUPPORTED_FILE_TYPE = "UNSUPPORTED_FILE_TYPE"
    FILE_TOO_LARGE = "FILE_TOO_LARGE"
    INVALID_FILE = "INVALID_FILE"
    EXTRACTION_FAILED = "EXTRACTION_FAILED"
    AI_SERVICE_UNAVAILABLE = "AI_SERVICE_UNAVAILABLE"
    PROCESSING_FAILED = "PROCESSING_FAILED"
    INTERNAL_ERROR = "INTERNAL_ERROR"


# Client-facing HTTP status per error code.
_STATUS = {
    ErrorCode.EMPTY_UPLOAD: 400,
    ErrorCode.UNSUPPORTED_FILE_TYPE: 415,
    ErrorCode.FILE_TOO_LARGE: 413,
    ErrorCode.INVALID_FILE: 400,
    ErrorCode.EXTRACTION_FAILED: 422,
    ErrorCode.AI_SERVICE_UNAVAILABLE: 503,
    ErrorCode.PROCESSING_FAILED: 500,
    ErrorCode.INTERNAL_ERROR: 500,
}


class ServiceError(Exception):
    """A controlled error whose ``code``/``message`` are safe to return."""

    def __init__(self, code: str, message: str, *, internal: str | None = None) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.internal = internal or message

    @property
    def http_status(self) -> int:
        return _STATUS.get(self.code, 500)
