"""
api/deps.py

Process-wide singletons: API settings and the stateless orchestration service.
Built once at import time so every request reuses the same configuration.
"""

from __future__ import annotations

from functools import lru_cache

from api.services.resume_service import ResumeApiService
from api.settings import ApiSettings, load_api_settings


@lru_cache(maxsize=1)
def get_settings() -> ApiSettings:
    return load_api_settings()


@lru_cache(maxsize=1)
def get_service() -> ResumeApiService:
    return ResumeApiService(get_settings())
