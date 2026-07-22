"""
run_intelligence.py

CLI entry point for the Resume Intelligence Layer.

Reads a parsed ``sections.json`` (the ONLY input — the PDF is never touched
again) and produces ``structured_resume.json`` plus validation/confidence
reports using Qwen 2.5 3B Instruct via Ollama.

Usage:
    python run_intelligence.py [path-to-sections.json] [--offline] [--model NAME]

Defaults to ``output/sections.json``. ``--offline`` uses a deterministic mock
client so the pipeline can be verified without a running model; if Ollama is
not reachable the tool automatically falls back to the mock.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

from config import OUTPUT_DIR
from llm.llm_config import load_config
from llm.resume_parser import ResumeIntelligence, build_client
from utils.logger import get_logger

logger = get_logger()

DEFAULT_SECTIONS_PATH = OUTPUT_DIR / "sections.json"


def run(sections_path: Path, offline: bool, model_override: str | None) -> int:
    logger.info("Resume Intelligence Layer starting")

    if not sections_path.exists():
        logger.error("sections.json not found: %s (run main.py first)", sections_path)
        return 1

    try:
        sections_json = json.loads(sections_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        logger.error("Could not read sections.json: %s", exc)
        return 1

    if not isinstance(sections_json, dict):
        logger.error("sections.json is not a JSON object.")
        return 1

    config = load_config()
    if model_override:
        config = config.__class__(**{**config.__dict__, "model": model_override})

    logger.info("Model: %s | Ollama: %s", config.model, config.base_url)

    client = build_client(offline, config)
    intelligence = ResumeIntelligence(client, config, OUTPUT_DIR)
    intelligence.run(sections_json)
    return 0


def main() -> None:
    args = sys.argv[1:]
    offline = "--offline" in args
    args = [a for a in args if a != "--offline"]

    model_override: str | None = None
    if "--model" in args:
        idx = args.index("--model")
        if idx + 1 < len(args):
            model_override = args[idx + 1]
            del args[idx : idx + 2]

    sections_path = Path(args[0]) if args else DEFAULT_SECTIONS_PATH
    sys.exit(run(sections_path, offline, model_override))


if __name__ == "__main__":
    main()
