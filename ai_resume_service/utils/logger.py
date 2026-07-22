"""
logger.py

A small, reusable console logger for the AI Resume Service.

Milestone 1 only needs clean, readable stdout logging (start, file detected,
extraction started/completed, timing, errors). This wraps the standard
library `logging` module with a consistent ``[LEVEL] message`` format so it
is easy to read in a terminal and easy to extend later (e.g. writing to a
file, or structured JSON logs for the future API milestone) without changing
any call sites.
"""

from __future__ import annotations

import logging
import sys

from config import LOG_LEVEL

_CONFIGURED = False


def get_logger(name: str = "ai_resume_service") -> logging.Logger:
    """Return a configured logger instance.

    Safe to call multiple times — the underlying root handler is only
    attached once to avoid duplicate log lines.
    """
    global _CONFIGURED

    logger = logging.getLogger(name)

    if not _CONFIGURED:
        handler = logging.StreamHandler(stream=sys.stdout)
        formatter = logging.Formatter("[%(levelname)s] %(message)s")
        handler.setFormatter(formatter)

        root = logging.getLogger()
        root.addHandler(handler)
        root.setLevel(LOG_LEVEL)
        _CONFIGURED = True

    return logger
