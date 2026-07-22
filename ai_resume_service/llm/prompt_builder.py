from __future__ import annotations

from pathlib import Path

BASE_DIR: Path = Path(__file__).resolve().parent.parent
PROMPTS_DIR: Path = BASE_DIR / "prompts"
SYSTEM_PROMPT_FILE = "system_prompt.txt"


class PromptBuilder:
    """Loads prompt templates and assembles section prompts.

    Prompt template files are immutable at runtime, so they are cached at the
    class level and read from disk only once per process — even though a fresh
    ``PromptBuilder`` is created for each request.
    """

    _cache: dict[str, str] = {}  # class-level: (dir|filename) -> template text

    def __init__(self, prompts_dir: Path = PROMPTS_DIR) -> None:
        self._dir = prompts_dir
        self._system = self._load(SYSTEM_PROMPT_FILE)

    @property
    def system_prompt(self) -> str:
        return self._system

    def build(
        self,
        prompt_file: str,
        section_key: str,
        section_text: str,
        *,
        auxiliary_text: str = "",
        completeness_hint: bool = False,
    ) -> tuple[str, str]:
        """Return (system_prompt, user_prompt) for a section.

        ``auxiliary_text`` (skills only) supplies limited recovery context from a
        potentially polluted neighboring section, clearly separated from the
        primary text. ``completeness_hint`` appends a one-off instruction used by
        the targeted projects re-extraction retry.
        """
        template = self._load(prompt_file)
        parts = [
            template,
            "",
            f"--- PRIMARY {section_key.upper()} SECTION TEXT (verbatim from parser) ---",
            section_text.strip(),
            "--- END PRIMARY SECTION TEXT ---",
        ]

        if auxiliary_text.strip():
            parts += [
                "",
                "--- AUXILIARY RECOVERY CONTEXT ---",
                "(This text belongs to a neighboring section that the upstream parser may have"
                " mixed together. Use it ONLY to recover skills that are explicitly present."
                " Do NOT treat spoken languages or prose sentences here as skills.)",
                auxiliary_text.strip(),
                "--- END AUXILIARY RECOVERY CONTEXT ---",
            ]

        if completeness_hint:
            parts += [
                "",
                "IMPORTANT: The previous response appears INCOMPLETE. Re-read EVERY line of the "
                "primary section text above and extract EVERY explicitly listed project. Do not "
                "omit any entry and do not merge multiple projects into one.",
            ]

        parts += ["", "Return ONLY the JSON described above."]
        return self._system, "\n".join(parts)

    def _load(self, filename: str) -> str:
        key = f"{self._dir}|{filename}"  # full path key (cache is class-level)
        if key not in self._cache:
            path = self._dir / filename
            self._cache[key] = path.read_text(encoding="utf-8").strip()
        return self._cache[key]
