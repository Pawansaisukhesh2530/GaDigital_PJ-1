"""
api/services/ollama_health.py

Lightweight, read-only Ollama availability checks used to guarantee that a real
API request is served by the real model — never a silent mock fallback
(Part 10). Implemented in the API layer via the local ``/api/tags`` endpoint so
the frozen ``qwen_client.py`` is not modified.
"""

from __future__ import annotations

import json
import urllib.error
import urllib.request
from dataclasses import dataclass


@dataclass(frozen=True)
class OllamaStatus:
    reachable: bool
    model_available: bool
    models: tuple[str, ...] = ()


def _installed_models(base_url: str, timeout: float) -> tuple[str, ...] | None:
    """Return the list of installed model names, or None if unreachable."""
    request = urllib.request.Request(f"{base_url.rstrip('/')}/api/tags", method="GET")
    try:
        with urllib.request.urlopen(request, timeout=timeout) as resp:
            body = json.loads(resp.read().decode("utf-8"))
    except (urllib.error.URLError, TimeoutError, json.JSONDecodeError, OSError):
        return None
    models = body.get("models") or []
    names = tuple(m.get("name", "") for m in models if isinstance(m, dict))
    return names


def _model_matches(requested: str, installed: tuple[str, ...]) -> bool:
    """True if ``requested`` matches an installed model (tolerating :latest)."""
    if requested in installed:
        return True
    base = requested.split(":", 1)[0]
    for name in installed:
        if name == requested or name.split(":", 1)[0] == base:
            return True
    return False


def check_ollama(base_url: str, model: str, timeout: float = 5.0) -> OllamaStatus:
    """Check Ollama reachability and whether ``model`` is installed."""
    names = _installed_models(base_url, timeout)
    if names is None:
        return OllamaStatus(reachable=False, model_available=False)
    return OllamaStatus(
        reachable=True,
        model_available=_model_matches(model, names),
        models=names,
    )
