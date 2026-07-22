from __future__ import annotations

import json
import re
import urllib.error
import urllib.request
from typing import Protocol

from llm.llm_config import LlmConfig
from schemas.resume_schema import PERSONAL_FIELDS


class QwenError(RuntimeError):
    """Raised when the model cannot be reached or returns an error."""


class LlmClient(Protocol):
    """Interface implemented by the real and mock clients."""

    def complete(
        self, system_prompt: str, user_prompt: str, *, section_key: str, section_text: str
    ) -> str:
        """Return the model's raw text response (expected to be JSON)."""
        ...


class OllamaQwenClient:
    """Talks to a local Ollama server running Qwen 2.5 3B Instruct."""

    def __init__(self, config: LlmConfig) -> None:
        self._config = config

    def complete(
        self, system_prompt: str, user_prompt: str, *, section_key: str, section_text: str
    ) -> str:
        payload = {
            "model": self._config.model,
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            "stream": False,
            "format": "json",
            # Keep the model resident between requests to avoid the cold-load
            # penalty on the first section of subsequent resumes (perf only;
            # no effect on output quality).
            "keep_alive": self._config.keep_alive,
            "options": {
                "temperature": self._config.temperature,
                "top_p": self._config.top_p,
                "num_ctx": self._config.num_ctx,
            },
        }
        request = urllib.request.Request(
            f"{self._config.base_url}/api/chat",
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        try:
            with urllib.request.urlopen(request, timeout=self._config.timeout_seconds) as resp:
                body = json.loads(resp.read().decode("utf-8"))
        except urllib.error.URLError as exc:
            raise QwenError(
                f"Could not reach Ollama at {self._config.base_url} "
                f"(is it running, and is '{self._config.model}' pulled?): {exc}"
            ) from exc
        except (TimeoutError, json.JSONDecodeError) as exc:
            raise QwenError(f"Invalid response from Ollama: {exc}") from exc

        message = body.get("message") or {}
        content = message.get("content", "")
        if not content:
            raise QwenError("Ollama returned an empty response.")
        return content

    def health_check(self) -> bool:
        """Return True if the Ollama server is reachable."""
        try:
            request = urllib.request.Request(f"{self._config.base_url}/api/tags", method="GET")
            with urllib.request.urlopen(request, timeout=5):
                return True
        except OSError:
            return False


class MockQwenClient:
    """Deterministic offline stand-in — maps section text into valid JSON.

    It never fabricates facts: it only reshapes the text it is given. Intended
    solely for verifying the pipeline when Ollama is unavailable.
    """

    _URL = re.compile(r"(?:https?://)?(?:www\.)?[A-Za-z0-9.\-]+\.[A-Za-z]{2,}(?:/[^\s]*)?")
    _EMAIL = re.compile(r"[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}")
    _PHONE = re.compile(r"\+?\d[\d\-\s().]{6,}\d")

    def complete(
        self, system_prompt: str, user_prompt: str, *, section_key: str, section_text: str
    ) -> str:
        lines = [ln.strip() for ln in section_text.split("\n") if ln.strip()]
        builder = {
            "personal": self._personal,
            "summary": self._summary,
            "education": self._education,
            "experience": self._experience,
            "projects": self._projects,
            "skills": self._skills,
            "languages": self._languages,
        }.get(section_key, lambda _l: {})
        return json.dumps(builder(lines))

    # -- deterministic reshapers -------------------------------------------

    def _personal(self, lines: list[str]) -> dict:
        joined = "\n".join(lines)
        email = self._EMAIL.search(joined)
        phone = self._PHONE.search(joined)
        out = {f: None for f in PERSONAL_FIELDS}
        out["email"] = email.group(0) if email else None
        out["phone"] = phone.group(0).strip() if phone else None
        # Strip emails first so their domain is not mistaken for a portfolio URL.
        url_source = self._EMAIL.sub("", joined)
        for url in self._URL.findall(url_source):
            low = url.lower()
            if "linkedin" in low:
                out["linkedin"] = url
            elif "github" in low:
                out["github"] = url
            elif out["portfolio"] is None:
                out["portfolio"] = url
        return out

    def _summary(self, lines: list[str]) -> dict:
        return {"summary": " ".join(lines) if lines else None}

    def _education(self, lines: list[str]) -> list[dict]:
        return [
            {
                "degree": line,
                "institution": None,
                "field_of_study": None,
                "start_date": None,
                "end_date": None,
                "grade": None,
                "description": None,
            }
            for line in lines
        ]

    def _experience(self, lines: list[str]) -> list[dict]:
        if not lines:
            return []
        return [
            {
                "job_title": lines[0],
                "company": lines[1] if len(lines) > 1 else None,
                "location": None,
                "employment_type": None,
                "start_date": None,
                "end_date": None,
                "currently_working": False,
                "description": lines[2:],
            }
        ]

    def _projects(self, lines: list[str]) -> list[dict]:
        return [{"project_name": line, "description": None, "technologies": []} for line in lines]

    def _skills(self, lines: list[str]) -> dict:
        from schemas.resume_schema import SKILL_CATEGORIES

        out = {c: [] for c in SKILL_CATEGORIES}
        out["other"] = lines
        return out

    def _languages(self, lines: list[str]) -> list[dict]:
        result = []
        for line in lines:
            match = re.match(r"^(.*?)\s*[\(:]\s*(.*?)\)?$", line)
            if match and match.group(2):
                result.append({"language": match.group(1).strip(), "proficiency": match.group(2).strip()})
            else:
                result.append({"language": line, "proficiency": None})
        return result
