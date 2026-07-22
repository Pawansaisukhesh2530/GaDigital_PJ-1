"""
llm_config.py

Loads Resume Intelligence settings from ``config/llm_config.json`` with
environment-variable overrides. Kept separate from the parser's root
``config.py`` so the two layers stay independent (the config directory is
read as a data file, never imported as a package).
"""

from __future__ import annotations

import json
import os
from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path

BASE_DIR: Path = Path(__file__).resolve().parent.parent
CONFIG_PATH: Path = BASE_DIR / "config" / "llm_config.json"

_DEFAULTS: dict = {
    "model": "qwen2.5:3b-instruct",
    "base_url": "http://localhost:11434",
    "temperature": 0.0,
    "top_p": 0.9,
    "num_ctx": 4096,
    "timeout_seconds": 120,
    "max_retries": 3,
    # How long Ollama keeps the model resident after a request. Keeping it warm
    # avoids the ~4s cold-load penalty on the first section of subsequent
    # resumes when uploads are sporadic. Accepts a duration string (e.g. "30m",
    # "1h"), seconds, or -1 for indefinite.
    "keep_alive": "30m",
}


@dataclass(frozen=True)
class LlmConfig:
    """Immutable configuration for the LLM client."""

    model: str
    base_url: str
    temperature: float
    top_p: float
    num_ctx: int
    timeout_seconds: int
    max_retries: int
    keep_alive: str


@lru_cache(maxsize=1)
def load_config() -> LlmConfig:
    """Load config from JSON (if present) then apply env overrides.

    Cached: the config file and environment are immutable for the lifetime of
    a running server/CLI process, so this is read and parsed only once instead
    of on every request. The returned dataclass is frozen (safe to share).
    """
    data = dict(_DEFAULTS)

    try:
        if CONFIG_PATH.exists():
            data.update(json.loads(CONFIG_PATH.read_text(encoding="utf-8")))
    except (OSError, json.JSONDecodeError):
        pass  # fall back to defaults

    data["model"] = os.getenv("QWEN_MODEL", data["model"])
    data["base_url"] = os.getenv("OLLAMA_BASE_URL", data["base_url"])
    data["max_retries"] = int(os.getenv("LLM_MAX_RETRIES", data["max_retries"]))
    data["keep_alive"] = os.getenv("OLLAMA_KEEP_ALIVE", data["keep_alive"])

    return LlmConfig(
        model=str(data["model"]),
        base_url=str(data["base_url"]).rstrip("/"),
        temperature=float(data["temperature"]),
        top_p=float(data["top_p"]),
        num_ctx=int(data["num_ctx"]),
        timeout_seconds=int(data["timeout_seconds"]),
        max_retries=int(data["max_retries"]),
        keep_alive=str(data["keep_alive"]),
    )
