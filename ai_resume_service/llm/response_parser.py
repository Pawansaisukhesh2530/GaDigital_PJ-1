from __future__ import annotations

import json

_FENCE_TOKENS = ("```json", "```JSON", "```")


class ResponseParseError(ValueError):
    """Raised when no valid JSON can be recovered from a response."""


def parse_response(raw: str) -> object:
    """Parse ``raw`` into a dict/list, tolerating fences and surrounding prose."""
    if raw is None:
        raise ResponseParseError("Empty response.")

    text = raw.strip()
    for token in _FENCE_TOKENS:
        if text.startswith(token):
            text = text[len(token):]
            if text.rstrip().endswith("```"):
                text = text.rstrip()[:-3]
            break
    text = text.strip()

    try:
        return json.loads(text)
    except json.JSONDecodeError:
        pass

    snippet = _extract_json_span(text)
    if snippet is None:
        raise ResponseParseError("No JSON object or array found in response.")
    try:
        return json.loads(snippet)
    except json.JSONDecodeError as exc:
        raise ResponseParseError(f"Recovered JSON is invalid: {exc}") from exc


def _extract_json_span(text: str) -> str | None:
    """Return the first balanced {...} or [...] span, honoring string quoting."""
    start = _first_index(text, "{", "[")
    if start is None:
        return None

    open_char = text[start]
    close_char = "}" if open_char == "{" else "]"
    depth = 0
    in_string = False
    escape = False

    for i in range(start, len(text)):
        char = text[i]
        if in_string:
            if escape:
                escape = False
            elif char == "\\":
                escape = True
            elif char == '"':
                in_string = False
            continue
        if char == '"':
            in_string = True
        elif char == open_char:
            depth += 1
        elif char == close_char:
            depth -= 1
            if depth == 0:
                return text[start : i + 1]
    return None


def _first_index(text: str, *chars: str) -> int | None:
    indices = [text.find(c) for c in chars if text.find(c) != -1]
    return min(indices) if indices else None
