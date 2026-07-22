"""
api/settings.py

Environment-based configuration for the FastAPI bridge. No machine-specific
absolute paths are hardcoded — everything resolves relative to the service
directory or is overridable via environment variables (Part 16).

The LLM model / base URL default to the frozen engine's own configuration
(``llm.llm_config.load_config``) so the API and CLI stay in sync, while still
allowing explicit ``OLLAMA_MODEL`` / ``OLLAMA_BASE_URL`` overrides.
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv

from llm.llm_config import load_config

load_dotenv()

BASE_DIR: Path = Path(__file__).resolve().parent.parent

_TRUE = {"1", "true", "yes", "on"}


def _env_bool(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    return raw.strip().lower() in _TRUE


def _env_list(name: str, default: list[str]) -> list[str]:
    raw = os.getenv(name)
    if not raw:
        return default
    return [origin.strip() for origin in raw.split(",") if origin.strip()]


@dataclass(frozen=True)
class ApiSettings:
    host: str
    port: int
    ollama_base_url: str
    ollama_model: str
    max_upload_bytes: int
    allowed_origins: list[str]
    keep_runtime_files: bool
    runtime_dir: Path


def load_api_settings() -> ApiSettings:
    """Build API settings from the environment, defaulting to engine config."""
    engine = load_config()

    max_mb = int(os.getenv("MAX_UPLOAD_SIZE_MB", os.getenv("MAX_FILE_SIZE_MB", "10")))
    runtime_dir_raw = os.getenv("RUNTIME_DIRECTORY", "runtime")
    runtime_dir = Path(runtime_dir_raw)
    if not runtime_dir.is_absolute():
        runtime_dir = BASE_DIR / runtime_dir

    return ApiSettings(
        host=os.getenv("HOST", "127.0.0.1"),
        port=int(os.getenv("PORT", "8000")),
        ollama_base_url=os.getenv("OLLAMA_BASE_URL", engine.base_url).rstrip("/"),
        ollama_model=os.getenv("OLLAMA_MODEL", engine.model),
        max_upload_bytes=max_mb * 1024 * 1024,
        allowed_origins=_env_list(
            "ALLOWED_ORIGINS",
            ["http://localhost", "http://127.0.0.1", "http://localhost:80"],
        ),
        keep_runtime_files=_env_bool("KEEP_RUNTIME_FILES", False),
        runtime_dir=runtime_dir,
    )
