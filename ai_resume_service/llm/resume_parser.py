from __future__ import annotations

import json
import time
from dataclasses import dataclass, field
from pathlib import Path

from llm.llm_config import LlmConfig
from llm.prompt_builder import PromptBuilder
from llm.qwen_client import LlmClient, MockQwenClient, OllamaQwenClient, QwenError
from llm.response_merger import ResponseMerger
from llm.response_parser import ResponseParseError, parse_response
from llm.section_postprocess import (
    count_project_candidates,
    postprocess_payload,
)
from schemas.confidence import overall_confidence, section_confidence
from schemas.resume_schema import SECTION_SPECS, empty_section
from schemas.validators import validate_section
from utils.logger import get_logger

_SKILL_AUX_SOURCES = ("languages",)

logger = get_logger()


@dataclass
class SectionRun:
    """Records what happened while extracting one section."""

    key: str
    present: bool
    data: object
    valid: bool
    errors: list[str] = field(default_factory=list)
    retries: int = 0
    normalizations: list[dict] = field(default_factory=list)


class ResumeIntelligence:
    """Runs section-aware LLM extraction over a parsed resume."""

    def __init__(self, client: LlmClient, config: LlmConfig, output_dir: Path) -> None:
        self._client = client
        self._config = config
        self._output_dir = output_dir
        self._prompts = PromptBuilder()
        self._merger = ResponseMerger()
        self._prompt_log: list[str] = []
        self._response_log: list[str] = []
        self._normalizations: list[dict] = []
        self._under_extraction: list[dict] = []

    # -- public API ---------------------------------------------------------

    def run(self, sections_json: dict) -> dict:
        """Extract, validate, merge and persist. Returns the structured resume."""
        start = time.perf_counter()
        self._normalizations = []
        self._under_extraction = []
        section_results: dict[str, object] = {}
        section_reports: dict[str, dict] = {}
        validation_report: dict[str, list[str]] = {}

        for spec in SECTION_SPECS:
            source = sections_json.get(spec.source_key, {})
            raw_text = source.get("raw_text", "") if isinstance(source, dict) else ""
            auxiliary_text = self._collect_auxiliary(spec.key, sections_json)

            run = self._extract_section(spec, raw_text, auxiliary_text)
            if spec.key == "projects":
                run = self._ensure_project_completeness(spec, raw_text, run)

            section_results[spec.key] = run.data
            validation_report[spec.key] = run.errors
            section_reports[spec.key] = section_confidence(
                spec.key,
                run.data,
                present=run.present,
                valid=run.valid,
                error_count=len(run.errors),
                retries=run.retries,
            )

        parser_conf = sections_json.get("metadata", {}).get("confidence", 0.0)
        parser_version = sections_json.get("metadata", {}).get("parser_version", "")
        overall = overall_confidence(section_reports, parser_conf)

        resume = self._merger.merge(
            section_results,
            sections_json,
            model=self._config.model,
            parser_version=parser_version,
            overall_confidence=overall,
        )

        self._write_outputs(
            resume, validation_report, section_reports, overall, self._merger.provenance
        )

        elapsed = time.perf_counter() - start
        logger.info("Intelligence layer finished in %.2fs (overall confidence %.2f)", elapsed, overall)
        return resume

    # -- per-section extraction with retry ---------------------------------

    def _extract_section(self, spec, raw_text: str, auxiliary_text: str = "") -> SectionRun:
        if not raw_text.strip() and not auxiliary_text.strip():
            logger.info("[%s] no source text; using empty default", spec.key)
            return SectionRun(spec.key, present=False, data=empty_section(spec.kind), valid=True)

        system_prompt, user_prompt = self._prompts.build(
            spec.prompt_file, spec.key, raw_text, auxiliary_text=auxiliary_text
        )
        self._prompt_log.append(f"===== PROMPT: {spec.key} =====\n{user_prompt}\n")
        logger.info("[%s] prompt generated (%d chars)", spec.key, len(user_prompt))

        best: SectionRun | None = None
        for attempt in range(1, self._config.max_retries + 1):
            logger.info("[%s] inference started (attempt %d/%d)", spec.key, attempt, self._config.max_retries)
            try:
                raw = self._client.complete(
                    system_prompt, user_prompt, section_key=spec.key, section_text=raw_text
                )
            except QwenError as exc:
                logger.error("[%s] model error: %s", spec.key, exc)
                self._response_log.append(f"===== {spec.key} attempt {attempt}: ERROR =====\n{exc}\n")
                best = SectionRun(spec.key, True, empty_section(spec.kind), False, [str(exc)], attempt - 1)
                break

            self._response_log.append(f"===== {spec.key} attempt {attempt} =====\n{raw}\n")
            logger.info("[%s] inference completed (attempt %d)", spec.key, attempt)

            try:
                parsed = parse_response(raw)
            except ResponseParseError as exc:
                logger.warning("[%s] unparseable response: %s", spec.key, exc)
                best = SectionRun(spec.key, True, empty_section(spec.kind), False, [str(exc)], attempt - 1)
                continue

            # Normalize first (key aliases + dates), validate second (Parts 2, 7, 8, 9).
            parsed, norms = postprocess_payload(spec.key, spec.kind, parsed)
            if norms:
                logger.info("[%s] applied %d deterministic normalization(s) pre-validation", spec.key, len(norms))

            valid, errors, normalized = validate_section(spec.key, spec.kind, parsed)
            run = SectionRun(spec.key, True, normalized, valid, errors, attempt - 1, norms)
            if valid:
                logger.info("[%s] validation passed on attempt %d", spec.key, attempt)
                self._normalizations.extend(norms)
                return run

            logger.warning("[%s] validation failed (attempt %d): %s", spec.key, attempt, "; ".join(errors))
            best = run  # keep best-effort normalized data

        if best is None:
            best = SectionRun(
                spec.key, True, empty_section(spec.kind), False, ["exhausted retries"],
                self._config.max_retries - 1,
            )
        self._normalizations.extend(best.normalizations)
        return best

    def _collect_auxiliary(self, section_key: str, sections_json: dict) -> str:
        """Gather limited auxiliary recovery context for the skills extractor.

        Skills stranded in a polluted neighboring section (e.g. programming
        languages placed under LANGUAGES) can be recovered without modifying the
        ATS parser or sections.json (Parts 10, 11).
        """
        if section_key != "skills":
            return ""
        chunks: list[str] = []
        for source_key in _SKILL_AUX_SOURCES:
            source = sections_json.get(source_key, {})
            text = source.get("raw_text", "") if isinstance(source, dict) else ""
            if text.strip():
                chunks.append(text.strip())
        return "\n".join(chunks)

    def _ensure_project_completeness(self, spec, raw_text: str, run: SectionRun) -> SectionRun:
        """Conservatively detect and repair project under-extraction (Part 4).

        Runs at most ONE targeted re-extraction. Never invents projects; if the
        gap persists it is flagged as ``possible_under_extraction`` for audit.
        """
        candidates = count_project_candidates(raw_text)
        extracted = len(run.data) if isinstance(run.data, list) else 0

        if candidates < 2 or extracted >= candidates:
            return run

        logger.warning(
            "[projects] possible under-extraction: %d source candidate(s), %d extracted; retrying once",
            candidates, extracted,
        )
        system_prompt, user_prompt = self._prompts.build(
            spec.prompt_file, spec.key, raw_text, completeness_hint=True
        )
        self._prompt_log.append(f"===== PROMPT: projects (completeness retry) =====\n{user_prompt}\n")

        try:
            raw = self._client.complete(
                system_prompt, user_prompt, section_key=spec.key, section_text=raw_text
            )
            self._response_log.append(f"===== projects completeness retry =====\n{raw}\n")
            parsed = parse_response(raw)
            parsed, norms = postprocess_payload(spec.key, spec.kind, parsed)
            valid, errors, normalized = validate_section(spec.key, spec.kind, parsed)
            retry_count = len(normalized) if isinstance(normalized, list) else 0
            if retry_count > extracted:
                logger.info("[projects] completeness retry recovered %d (was %d)", retry_count, extracted)
                self._normalizations.extend(norms)
                run = SectionRun(
                    spec.key, True, normalized, valid, errors, run.retries + 1, norms
                )
                extracted = retry_count
        except (QwenError, ResponseParseError) as exc:
            logger.warning("[projects] completeness retry failed: %s", exc)

        if extracted < candidates:
            self._under_extraction.append({
                "section": "projects",
                "source_candidate_count": candidates,
                "extracted_count": extracted,
                "note": "possible_under_extraction",
            })
        return run

    # -- output persistence -------------------------------------------------

    def _write_outputs(
        self,
        resume: dict,
        validation_report: dict,
        section_reports: dict,
        overall: float,
        provenance: dict[str, str],
    ) -> None:
        self._output_dir.mkdir(parents=True, exist_ok=True)

        (self._output_dir / "structured_resume.json").write_text(
            json.dumps(resume, indent=2, ensure_ascii=False), encoding="utf-8"
        )
        (self._output_dir / "prompt_log.txt").write_text(
            "\n".join(self._prompt_log), encoding="utf-8"
        )
        (self._output_dir / "llm_response.txt").write_text(
            "\n".join(self._response_log), encoding="utf-8"
        )
        (self._output_dir / "validation_report.json").write_text(
            json.dumps(
                {
                    "sections": validation_report,
                    "passed": {k: not v for k, v in validation_report.items()},
                    "normalizations": self._normalizations,
                    "under_extraction": self._under_extraction,
                    "field_provenance": provenance,
                },
                indent=2,
                ensure_ascii=False,
            ),
            encoding="utf-8",
        )
        (self._output_dir / "confidence_report.json").write_text(
            json.dumps(
                {"sections": section_reports, "overall": overall}, indent=2, ensure_ascii=False
            ),
            encoding="utf-8",
        )
        logger.info("Structured resume + reports written to %s", self._output_dir)


def build_client(offline: bool, config: LlmConfig) -> LlmClient:
    """Choose the real Ollama client, or the offline mock when requested/needed."""
    if offline:
        logger.warning("LLM Provider: Mock | Mode: OFFLINE/FALLBACK | Reason: --offline flag requested")
        logger.warning("OFFLINE MODE: using deterministic mock client (no real LLM inference).")
        return MockQwenClient()

    client = OllamaQwenClient(config)
    if not client.health_check():
        logger.warning(
            "LLM Provider: Mock | Mode: OFFLINE/FALLBACK | Reason: Ollama not reachable at %s",
            config.base_url,
        )
        logger.warning(
            "Ollama not reachable at %s; falling back to offline mock. "
            "Start Ollama and `ollama pull %s` for real inference.",
            config.base_url,
            config.model,
        )
        return MockQwenClient()

    logger.info("LLM Provider: Ollama | Model: %s | Mode: REAL", config.model)
    return client
