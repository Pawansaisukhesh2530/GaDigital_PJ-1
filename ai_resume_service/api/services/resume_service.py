"""
api/services/resume_service.py

Per-request orchestration of the FROZEN pipeline inside an isolated workspace:

    save upload -> detect/extract -> ATS parse -> Ollama (REAL) intelligence
    -> output sanity -> response -> cleanup

Concurrency safety (Parts 5, 6): every request gets its own UUID workspace under
``runtime/<uuid>/`` and its own fresh parser / intelligence / client instances.
Nothing is written to the shared ``output/`` directory, so two candidates can
never overwrite each other's files.

Real-model guarantee (Part 10): the API builds ``OllamaQwenClient`` directly and
pre-checks model availability. It NEVER falls back to ``MockQwenClient``.
"""

from __future__ import annotations

import dataclasses
import json
import shutil
import time
from pathlib import Path

from api.errors import ErrorCode, ServiceError
from api.services.ollama_health import OllamaStatus, check_ollama
from api.settings import ApiSettings
from api.utils import file_security
from api.utils.output_sanity import apply_output_sanity
from ats.ats_parser import AtsResumeParser
from extractors.extractor_factory import ExtractorFactory, UnsupportedFileTypeError
from llm.llm_config import load_config
from llm.qwen_client import OllamaQwenClient, QwenError
from llm.resume_parser import ResumeIntelligence
from utils.logger import get_logger

logger = get_logger()


class ResumeApiService:
    """Stateless orchestrator; safe to share across concurrent requests."""

    def __init__(self, settings: ApiSettings) -> None:
        self._settings = settings

    # -- health -------------------------------------------------------------

    def health(self) -> dict:
        """Report parser availability and live Ollama/model status (Part 11)."""
        status = check_ollama(self._settings.ollama_base_url, self._settings.ollama_model)
        ok = status.reachable and status.model_available
        return {
            "status": "ok" if ok else "degraded",
            "parser": True,
            "ollama": status.reachable and status.model_available,
            "model": self._settings.ollama_model,
        }

    # -- main flow ----------------------------------------------------------

    def process(self, request_id: str, filename: str | None, content: bytes) -> tuple[dict, dict]:
        """Run the full pipeline for one upload. Returns (structured_resume, meta)."""
        started = time.perf_counter()
        logger.info("[%s] request started (filename provided=%s)", request_id, bool(filename))

        # 1) File validation (extension + size + magic bytes) — Parts 8, 9.
        ext = file_security.safe_extension(filename)
        file_security.validate_size(len(content), self._settings.max_upload_bytes)
        file_security.verify_signature(content, ext)
        logger.info("[%s] file validation passed (ext=%s, bytes=%d)", request_id, ext, len(content))

        # 2) Pre-flight: real Ollama + model must be available (Part 10).
        status = check_ollama(self._settings.ollama_base_url, self._settings.ollama_model)
        self._require_ollama(request_id, status)

        workspace = self._create_workspace(request_id)
        try:
            resume, meta = self._run_pipeline(request_id, workspace, ext, content)
        finally:
            self._cleanup(request_id, workspace)

        elapsed_ms = int((time.perf_counter() - started) * 1000)
        meta["processing_time_ms"] = elapsed_ms
        logger.info("[%s] request completed in %d ms (confidence=%.2f)",
                    request_id, elapsed_ms, meta.get("confidence", 0.0))
        return resume, meta

    # -- pipeline stages ----------------------------------------------------

    def _run_pipeline(self, request_id: str, workspace: Path, ext: str, content: bytes) -> tuple[dict, dict]:
        resume_path = workspace / file_security.internal_filename(ext)
        resume_path.write_bytes(content)

        # Extraction (frozen extractors).
        try:
            detection = ExtractorFactory.detect(resume_path)
            extractor = ExtractorFactory.get_extractor(resume_path)
        except UnsupportedFileTypeError as exc:
            raise ServiceError(ErrorCode.UNSUPPORTED_FILE_TYPE,
                               "Only PDF, DOC and DOCX files are supported.",
                               internal=str(exc)) from exc

        logger.info("[%s] parser started (type=%s)", request_id, detection.file_type.value)
        try:
            extraction = extractor.extract()
        except Exception as exc:  # noqa: BLE001 - never leak a stack trace
            raise ServiceError(ErrorCode.EXTRACTION_FAILED,
                               "The document could not be read.",
                               internal=f"extractor error: {exc}") from exc

        if not extraction.success or not (extraction.text or "").strip():
            raise ServiceError(ErrorCode.EXTRACTION_FAILED,
                               "No readable text could be extracted from the document.",
                               internal=f"extraction message: {extraction.message}")

        # ATS layout parsing (frozen parser) — writes nothing globally.
        parser = AtsResumeParser()
        parse_result = parser.parse(resume_path, detection.file_type, extraction.text)
        sections_document = parse_result.document
        (workspace / "sections.json").write_text(
            json.dumps(sections_document, indent=2, ensure_ascii=False), encoding="utf-8"
        )
        logger.info("[%s] parser completed (layout=%s, parser_confidence=%.2f)",
                    request_id,
                    sections_document.get("metadata", {}).get("layout"),
                    sections_document.get("metadata", {}).get("confidence", 0.0))

        # Resume Intelligence with REAL Ollama Qwen, isolated output dir.
        config = self._request_config()
        client = OllamaQwenClient(config)
        intelligence = ResumeIntelligence(client, config, workspace)

        logger.info("[%s] Qwen started (model=%s)", request_id, config.model)
        try:
            resume = intelligence.run(sections_document)
        except QwenError as exc:
            raise ServiceError(ErrorCode.AI_SERVICE_UNAVAILABLE,
                               "The AI service is currently unavailable. Please try again.",
                               internal=f"QwenError during run: {exc}") from exc
        except Exception as exc:  # noqa: BLE001 - controlled failure only
            raise ServiceError(ErrorCode.PROCESSING_FAILED,
                               "Resume processing failed.",
                               internal=f"intelligence error: {exc}") from exc
        logger.info("[%s] Qwen completed", request_id)

        # Final-output sanity (Parts 13, 14) — API boundary only.
        resume = apply_output_sanity(resume, sections_document)

        metadata = resume.get("metadata", {}) if isinstance(resume, dict) else {}
        confidence = float(metadata.get("confidence", 0.0) or 0.0)
        logger.info("[%s] validation/confidence resolved (confidence=%.2f)", request_id, confidence)

        meta = {
            "model": metadata.get("model", config.model),
            "parser_version": metadata.get("parser_version", ""),
            "confidence": confidence,
            "processing_time_ms": 0,  # filled by caller
        }
        return resume, meta

    # -- helpers ------------------------------------------------------------

    def _request_config(self):
        """Engine config with API model/base-url overrides applied."""
        base = load_config()
        return dataclasses.replace(
            base,
            model=self._settings.ollama_model,
            base_url=self._settings.ollama_base_url,
        )

    def _require_ollama(self, request_id: str, status: OllamaStatus) -> None:
        if not status.reachable:
            logger.error("[%s] Ollama unreachable at %s", request_id, self._settings.ollama_base_url)
            raise ServiceError(ErrorCode.AI_SERVICE_UNAVAILABLE,
                               "The AI service is currently unavailable. Please try again later.",
                               internal="Ollama /api/tags unreachable")
        if not status.model_available:
            logger.error("[%s] model %s not installed in Ollama", request_id, self._settings.ollama_model)
            raise ServiceError(ErrorCode.AI_SERVICE_UNAVAILABLE,
                               "The AI service is currently unavailable. Please try again later.",
                               internal=f"model '{self._settings.ollama_model}' not installed")

    def _create_workspace(self, request_id: str) -> Path:
        workspace = self._settings.runtime_dir / request_id
        workspace.mkdir(parents=True, exist_ok=True)
        return workspace

    def _cleanup(self, request_id: str, workspace: Path) -> None:
        if self._settings.keep_runtime_files:
            logger.info("[%s] KEEP_RUNTIME_FILES=true; retaining workspace", request_id)
            return
        try:
            runtime_root = self._settings.runtime_dir.resolve()
            target = workspace.resolve()
            # Safety: only ever delete inside the configured runtime directory.
            if runtime_root in target.parents and target.exists():
                shutil.rmtree(target, ignore_errors=True)
                logger.info("[%s] workspace cleaned up", request_id)
            else:
                logger.warning("[%s] refused to clean unexpected path", request_id)
        except OSError as exc:
            logger.warning("[%s] workspace cleanup failed: %s", request_id, exc)
