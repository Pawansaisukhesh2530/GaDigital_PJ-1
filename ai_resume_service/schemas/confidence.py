"""
confidence.py

Computes per-section and overall confidence for the structured resume
(Part 9). Confidence is deterministic and explainable — it blends three
signals:

    * validity     — did the section pass validation cleanly (1.0) or only
                     after coercion / with residual errors (lower)?
    * completeness — how much of the expected structure was actually filled.
    * retry cost   — each retry needed to obtain a valid response lowers trust.

A section with no source text in ``sections.json`` is reported as
``0.0`` with ``present=False`` and excluded from the overall average, so an
absent section never drags the score down.
"""

from __future__ import annotations

from schemas.resume_schema import SKILL_CATEGORIES

_RETRY_PENALTY = 0.15
_MAX_RETRIES = 3


def section_confidence(
    key: str,
    data: object,
    *,
    present: bool,
    valid: bool,
    error_count: int,
    retries: int,
) -> dict:
    """Return a confidence record for one section."""
    if not present:
        return {"present": False, "valid": True, "retries": 0, "confidence": 0.0}

    validity = 1.0 if valid else max(0.0, 1.0 - 0.2 * error_count)
    completeness = _completeness(key, data)
    penalty = _RETRY_PENALTY * min(retries, _MAX_RETRIES)
    score = max(0.0, min(1.0, 0.55 * validity + 0.45 * completeness - penalty))

    return {
        "present": True,
        "valid": valid,
        "retries": retries,
        "completeness": round(completeness, 2),
        "confidence": round(score, 2),
    }


def overall_confidence(section_reports: dict[str, dict], parser_confidence: float) -> float:
    """Blend present-section confidences with the upstream parser confidence."""
    present = [r["confidence"] for r in section_reports.values() if r.get("present")]
    llm_mean = sum(present) / len(present) if present else 0.0
    overall = 0.8 * llm_mean + 0.2 * float(parser_confidence or 0.0)
    return round(min(1.0, overall), 2)


def _completeness(key: str, data: object) -> float:
    """Fraction of the expected structure that is populated."""
    if key == "personal":
        if not isinstance(data, dict) or not data:
            return 0.0
        filled = sum(1 for v in data.values() if v)
        return filled / len(data)
    if key == "summary":
        return 1.0 if isinstance(data, str) and data.strip() else 0.0
    if key == "skills":
        if not isinstance(data, dict):
            return 0.0
        return 1.0 if any(data.get(c) for c in SKILL_CATEGORIES) else 0.0
    if isinstance(data, list):
        if not data:
            return 0.0
        # Average field-fill ratio across list items.
        ratios = []
        for item in data:
            if isinstance(item, dict) and item:
                filled = sum(1 for v in item.values() if v or v is True)
                ratios.append(filled / len(item))
        return sum(ratios) / len(ratios) if ratios else 0.0
    return 0.0
