"""
api/schemas/api_models.py

Pydantic models describing the HTTP contract. The structured resume itself is
passed through as a free-form object so the frozen ``structured_resume`` schema
is never redefined or constrained here (Part 12).
"""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field


class Meta(BaseModel):
    model: str
    parser_version: str
    confidence: float
    processing_time_ms: int


class ParseSuccess(BaseModel):
    success: bool = True
    request_id: str
    data: dict[str, Any]
    meta: Meta


class ErrorDetail(BaseModel):
    code: str
    message: str


class ErrorResponse(BaseModel):
    success: bool = False
    request_id: str
    error: ErrorDetail


class HealthResponse(BaseModel):
    status: str
    parser: bool
    ollama: bool
    model: str = Field(default="")
