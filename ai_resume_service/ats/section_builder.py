"""
section_builder.py

Stage 6: build sections from heading boundaries over reading-ordered lines.

Because the lines are already in reading order (header band, then column by
column, top to bottom), a simple "heading opens a section, following lines fill
it until the next heading" rule now respects the visual layout — content from a
different column no longer bleeds into an unrelated section, because columns are
contiguous in the stream. Lines that appear before the first heading are
returned separately as the *header band*, which Stage 7 mines for personal
information (they are never dumped into Summary).
"""

from __future__ import annotations

from ats.models import DetectedSection, OrderedLine

_PREAMBLE_TITLE = "(header)"


class SectionBuilder:
    """Groups reading-ordered lines into sections using heading boundaries."""

    def build(
        self, lines: list[OrderedLine]
    ) -> tuple[list[DetectedSection], list[OrderedLine]]:
        """Return (sections, header_band_lines)."""
        header_band: list[OrderedLine] = []
        sections: list[DetectedSection] = []
        current: DetectedSection | None = None
        buffer: list[str] = []

        for line in lines:
            if line.is_heading:
                if current is not None:
                    self._close(current, buffer)
                    sections.append(current)
                    buffer = []
                elif not sections:
                    # Lines seen before the very first heading = header band.
                    pass

                current = DetectedSection(
                    canonical=line.canonical or "others",
                    heading=line.text,
                    page=line.page,
                    column=line.column,
                    start_line=line.line_number,
                    end_line=line.line_number,
                    confidence=line.heading_score,
                    raw_text="",
                )
                continue

            if current is None:
                header_band.append(line)
            else:
                buffer.append(line.text)
                current.end_line = line.line_number

        if current is not None:
            self._close(current, buffer)
            sections.append(current)

        return sections, header_band

    @staticmethod
    def _close(section: DetectedSection, buffer: list[str]) -> None:
        section.raw_text = "\n".join(buffer).strip()
