"""
main.py

CLI entry point for Resume Text Extraction, Normalization, and Section
Detection.

Usage:
    python main.py <path-to-resume-file>

Example:
    python main.py tests/resume.pdf

Workflow:
    1. Validate the file path exists.
    2. Detect the file type.
    3. Extract readable text via the appropriate extractor.
    4. Save the extracted text to output/<filename>.txt.
    5. Normalize the extracted text and save it to output/normalized_resume.txt.
    6. Detect resume sections in the normalized text and save the grouped
       result to output/sections.json.
    7. Print a summary and a text preview to the terminal.

This script never raises uncaught exceptions for expected failure modes
(missing file, unsupported type, corrupted document, permission errors) —
all are reported as clean log messages with a non-zero exit code.
"""

from __future__ import annotations

import json
import sys
import time
from pathlib import Path

from ats.ats_parser import AtsResumeParser
from config import ensure_runtime_dirs, DEBUG_DIR, OUTPUT_DIR, SECTION_DEBUG
from extractors.extractor_factory import ExtractorFactory, UnsupportedFileTypeError
from normalization.text_normalizer import TextNormalizer
from utils.file_detector import FileType
from utils.logger import get_logger

logger = get_logger()

PREVIEW_CHAR_LIMIT = 500

NORMALIZED_OUTPUT_PATH = OUTPUT_DIR / "normalized_resume.txt"
SECTIONS_OUTPUT_PATH = OUTPUT_DIR / "sections.json"


def run(file_path: str) -> int:
    """Run the extraction workflow for a single file.

    Returns:
        Process exit code (0 on success, 1 on any handled failure).
    """
    logger.info("Starting resume text extraction")
    ensure_runtime_dirs()

    path = Path(file_path)
    logger.info("Processing: %s", path)

    if not path.exists():
        logger.error("File not found: %s", path)
        return 1

    if not path.is_file():
        logger.error("Path is not a file: %s", path)
        return 1

    try:
        detection = ExtractorFactory.detect(path)
        logger.info("Detected file type: %s", detection.file_type.value)

        extractor = ExtractorFactory.get_extractor(path)
    except UnsupportedFileTypeError as exc:
        logger.error("Unsupported file: %s", exc)
        return 1
    except PermissionError:
        logger.error("Permission denied while accessing: %s", path)
        return 1

    logger.info("Extraction started")
    start_time = time.perf_counter()

    try:
        result = extractor.extract()
    except PermissionError:
        logger.error("Permission denied while reading: %s", path)
        return 1
    except Exception as exc:  # noqa: BLE001 - last-resort safety net, never crash the CLI
        logger.error("Unexpected error during extraction: %s", exc)
        return 1

    elapsed = time.perf_counter() - start_time

    if not result.success:
        logger.warning("Extraction did not produce text: %s", result.message)
        logger.info("Time taken: %.2fs", elapsed)
        return 1

    logger.info("Pages found: %s", result.page_count)
    logger.info("Extraction completed successfully")
    logger.info("Time taken: %.2fs", elapsed)

    output_path = _save_output(path, result.text)
    logger.info("Output saved: %s", output_path)

    _run_normalization(result.text)
    _run_ats_parsing(path, detection.file_type, result.text)

    _print_preview(result.text)
    return 0


def _save_output(source_path: Path, text: str) -> Path:
    """Write extracted text to output/<source filename>.txt and return the path."""
    output_path = OUTPUT_DIR / f"{source_path.stem}.txt"
    output_path.write_text(text, encoding="utf-8")
    return output_path


def _run_normalization(raw_text: str) -> str:
    """Normalize raw extracted text and save it to ``NORMALIZED_OUTPUT_PATH``.

    Returns the normalized text so it can be passed on to section detection.
    Never raises — on failure, the raw text is written through unchanged and
    a warning is logged instead of crashing the CLI.
    """
    logger.info("Normalization started")
    start_time = time.perf_counter()

    result = TextNormalizer().normalize(raw_text)

    elapsed = time.perf_counter() - start_time
    for warning in result.warnings:
        logger.warning(warning)

    NORMALIZED_OUTPUT_PATH.write_text(result.text, encoding="utf-8")

    logger.info("Normalization finished")
    logger.info("Processing time: %.2fs", elapsed)
    logger.info("Output saved: %s", NORMALIZED_OUTPUT_PATH)

    return result.text


def _run_ats_parsing(path: Path, file_type: FileType, raw_text: str) -> None:
    """Run the layout-aware ATS pipeline and save the structured sections.json.

    Unlike the plain-text pipeline, this reads the document's geometry (from the
    original file) to reconstruct reading order before grouping sections, so it
    is robust to PDF storage-order scrambling and multi-column templates. When
    ``SECTION_DEBUG`` is enabled it also emits the per-stage debug artifacts.
    Never raises — any failure degrades to an ``others``-only result.
    """
    logger.info("ATS layout parsing started")
    start_time = time.perf_counter()

    parser = AtsResumeParser()
    result = parser.parse(path, file_type, raw_text)

    elapsed = time.perf_counter() - start_time
    for warning in result.warnings:
        logger.warning(warning)

    SECTIONS_OUTPUT_PATH.write_text(
        json.dumps(result.document, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )

    if SECTION_DEBUG:
        parser.write_debug_artifacts(result, DEBUG_DIR)
        logger.info("Debug artifacts saved: %s", DEBUG_DIR)

    metadata = result.document.get("metadata", {})
    detected = [
        key
        for key in result.document
        if key not in ("metadata", "personal_information", "others", "warnings")
        and result.document[key].get("raw_text")
    ]
    logger.info("Detected layout: %s (confidence %.2f)", metadata.get("layout"), metadata.get("confidence", 0.0))
    logger.info("Sections detected: %s", ", ".join(detected) if detected else "none")
    logger.info("ATS layout parsing finished")
    logger.info("Processing time: %.2fs", elapsed)
    logger.info("Output saved: %s", SECTIONS_OUTPUT_PATH)


def _print_preview(text: str) -> None:
    """Print a preview of the extracted text to the terminal."""
    preview = text[:PREVIEW_CHAR_LIMIT]
    suffix = "..." if len(text) > PREVIEW_CHAR_LIMIT else ""
    print("----- Extracted Text Preview -----")
    print(preview + suffix)
    print("-----------------------------------")


def main() -> None:
    if len(sys.argv) != 2:
        print("Usage: python main.py <path-to-resume-file>")
        sys.exit(1)

    exit_code = run(sys.argv[1])
    sys.exit(exit_code)


if __name__ == "__main__":
    main()
