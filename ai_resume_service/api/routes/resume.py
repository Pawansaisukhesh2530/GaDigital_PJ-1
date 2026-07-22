"""
api/routes/resume.py

HTTP routes: POST /api/resume/parse and GET /health.

The upload is read in bounded chunks so an oversized file is rejected without
loading it fully into memory (Part 9). All failures are converted to controlled
JSON error responses that carry the request_id and never leak internals.
"""

from __future__ import annotations

import uuid

from fastapi import APIRouter, File, UploadFile
from fastapi.concurrency import run_in_threadpool
from fastapi.responses import JSONResponse

from api.deps import get_service, get_settings
from api.errors import ErrorCode, ServiceError
from api.schemas.api_models import ErrorDetail, ErrorResponse, HealthResponse, Meta, ParseSuccess
from utils.logger import get_logger

logger = get_logger()

router = APIRouter()

_CHUNK = 1024 * 1024  # 1 MB


async def _read_bounded(file: UploadFile, max_bytes: int) -> bytes:
    """Read an upload in chunks, aborting early if it exceeds ``max_bytes``."""
    chunks: list[bytes] = []
    total = 0
    while True:
        chunk = await file.read(_CHUNK)
        if not chunk:
            break
        total += len(chunk)
        if total > max_bytes:
            limit_mb = max_bytes // (1024 * 1024)
            raise ServiceError(
                ErrorCode.FILE_TOO_LARGE,
                f"File exceeds the maximum allowed size of {limit_mb} MB.",
                internal=f"streamed size exceeded max_bytes={max_bytes}",
            )
        chunks.append(chunk)
    return b"".join(chunks)


def _error_response(request_id: str, err: ServiceError) -> JSONResponse:
    payload = ErrorResponse(
        request_id=request_id,
        error=ErrorDetail(code=err.code, message=err.message),
    )
    return JSONResponse(status_code=err.http_status, content=payload.model_dump())


@router.get("/health", response_model=HealthResponse)
async def health() -> JSONResponse:
    service = get_service()
    status = await run_in_threadpool(service.health)
    return JSONResponse(status_code=200, content=status)


@router.post("/api/resume/parse")
async def parse_resume(file: UploadFile = File(...)) -> JSONResponse:
    request_id = str(uuid.uuid4())
    settings = get_settings()
    service = get_service()

    try:
        if file is None or not file.filename:
            raise ServiceError(ErrorCode.EMPTY_UPLOAD, "No file was uploaded.")

        content = await _read_bounded(file, settings.max_upload_bytes)
        resume, meta = await run_in_threadpool(service.process, request_id, file.filename, content)

        response = ParseSuccess(
            request_id=request_id,
            data=resume,
            meta=Meta(**meta),
        )
        return JSONResponse(status_code=200, content=response.model_dump())

    except ServiceError as err:
        logger.warning("[%s] request failed: code=%s internal=%s", request_id, err.code, err.internal)
        return _error_response(request_id, err)
    except Exception as exc:  # noqa: BLE001 - last-resort guard; never leak details
        logger.exception("[%s] unexpected error: %s", request_id, exc)
        return _error_response(
            request_id,
            ServiceError(ErrorCode.INTERNAL_ERROR, "An unexpected error occurred."),
        )
    finally:
        try:
            await file.close()
        except Exception:  # noqa: BLE001
            pass
