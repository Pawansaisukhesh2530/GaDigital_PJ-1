"""
config.py

Centralized configuration for the AI Resume Service.

Milestone 1 scope: only the settings needed for CLI-based text extraction.
Values are loaded from environment variables (via a .env file if present) with
sensible defaults, so later milestones (API, OCR, AI parsing) can extend this
file without changing how existing code consumes it.
"""

from __future__ import annotations

from pathlib import Path

from dotenv import load_dotenv
import os

load_dotenv()

BASE_DIR: Path = Path(__file__).resolve().parent

OUTPUT_DIR: Path = BASE_DIR / os.getenv("OUTPUT_DIR", "output")

TEMP_DIR: Path = BASE_DIR / os.getenv("TEMP_DIR", "temp")

LOG_LEVEL: str = os.getenv("LOG_LEVEL", "INFO").upper()

# When enabled, section detection writes debug artifacts (normalized_lines.txt,
# heading_detection.json, section_boundaries.txt) to ``OUTPUT_DIR/debug``.
SECTION_DEBUG: bool = os.getenv("SECTION_DEBUG", "true").strip().lower() in ("1", "true", "yes", "on")

# Directory for section-detection debug artifacts.
DEBUG_DIR: Path = OUTPUT_DIR / "debug"


MAX_FILE_SIZE_MB: int = int(os.getenv("MAX_FILE_SIZE_MB", "10"))

SUPPORTED_EXTENSIONS: tuple[str, ...] = (".pdf", ".docx")

FUTURE_EXTENSIONS: tuple[str, ...] = (".doc", ".png", ".jpg", ".jpeg")


def ensure_runtime_dirs() -> None:
    """Create the output/temp directories if they do not already exist."""
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    TEMP_DIR.mkdir(parents=True, exist_ok=True)
