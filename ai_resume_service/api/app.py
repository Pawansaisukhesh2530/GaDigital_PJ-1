"""
api/app.py

FastAPI application factory for the CPVIA Resume Intelligence bridge.

Wires CORS (configurable, never "*"), the resume/health routes, and a global
exception handler that guarantees clients never receive stack traces or
internal details (Part 4).

Run locally:
    uvicorn api.app:app --host 127.0.0.1 --port 8000
"""

from __future__ import annotations

import uuid

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from api.deps import get_settings
from api.errors import ErrorCode
from api.routes.resume import router as resume_router
from utils.logger import get_logger

logger = get_logger()


def create_app() -> FastAPI:
    settings = get_settings()
    app = FastAPI(title="CPVIA Resume Intelligence API", version="1.0.0")

    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.allowed_origins,  # explicit local origins only
        allow_credentials=False,
        allow_methods=["GET", "POST"],
        allow_headers=["*"],
    )

    app.include_router(resume_router)

    @app.exception_handler(Exception)
    async def _unhandled(request: Request, exc: Exception) -> JSONResponse:
        request_id = str(uuid.uuid4())
        logger.exception("[%s] unhandled exception at %s: %s", request_id, request.url.path, exc)
        return JSONResponse(
            status_code=500,
            content={
                "success": False,
                "request_id": request_id,
                "error": {"code": ErrorCode.INTERNAL_ERROR, "message": "An unexpected error occurred."},
            },
        )

    logger.info("Resume Intelligence API initialized (model=%s, origins=%s)",
                settings.ollama_model, settings.allowed_origins)
    return app


app = create_app()
