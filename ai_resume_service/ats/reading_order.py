"""
reading_order.py

Stage 3: reconstruct the logical reading order from block geometry.

PDF text is returned in storage order; here we impose the order a human reads:
page by page, the full-width header band first, then each column left to
right, and within a column top to bottom. Consecutive blocks in the same
column that are vertically adjacent are merged so wrapped paragraphs stay
together. The result is a flat, correctly ordered list of :class:`OrderedLine`
tokens, each carrying its page/column and font metadata for heading detection.
"""

from __future__ import annotations

from ats.models import Block, OrderedLine
from ats.settings import READING_ORDER_SETTINGS


class ReadingOrderBuilder:
    """Reorders blocks into reading order and flattens them into lines."""

    def __init__(self) -> None:
        self._cfg = READING_ORDER_SETTINGS

    def build(self, blocks: list[Block]) -> tuple[list[Block], list[OrderedLine]]:
        """Return (ordered_blocks, ordered_lines) in logical reading order."""
        ordered = self._order_blocks(blocks)
        ordered = self._merge_adjacent(ordered)

        for priority, block in enumerate(ordered):
            block.reading_priority = priority

        lines = self._flatten(ordered)
        return ordered, lines

    def _order_blocks(self, blocks: list[Block]) -> list[Block]:
        """Sort by page, then band (top-to-bottom), then column (left-to-right).

        Within a band the columns are read fully left to right, each column top
        to bottom — the order a human reads a multi-column layout.
        """

        def sort_key(block: Block) -> tuple:
            return (
                block.page,
                block.band,
                block.column,
                round(block.y0, 1),
                round(block.x0, 1),
            )

        return sorted(blocks, key=sort_key)

    def _merge_adjacent(self, ordered: list[Block]) -> list[Block]:
        """Merge vertically adjacent same-column blocks into one block."""
        if not ordered:
            return []

        merged: list[Block] = [ordered[0]]
        for block in ordered[1:]:
            prev = merged[-1]
            if self._should_merge(prev, block):
                prev.lines.extend(block.lines)
                prev.x0 = min(prev.x0, block.x0)
                prev.y0 = min(prev.y0, block.y0)
                prev.x1 = max(prev.x1, block.x1)
                prev.y1 = max(prev.y1, block.y1)
            else:
                merged.append(block)
        return merged

    def _should_merge(self, prev: Block, block: Block) -> bool:
        if prev.page != block.page or prev.band != block.band or prev.column != block.column:
            return False
        gap = block.y0 - prev.y1
        font = max(block.font_size, prev.font_size, 1.0)
        return 0 <= gap <= font * self._cfg["merge_gap_font_multiple"]

    @staticmethod
    def _flatten(ordered: list[Block]) -> list[OrderedLine]:
        lines: list[OrderedLine] = []
        number = 0
        for block in ordered:
            for index, text_line in enumerate(block.lines):
                if not text_line.text:
                    continue
                number += 1
                lines.append(
                    OrderedLine(
                        text=text_line.text,
                        page=block.page,
                        column=block.column,
                        line_number=number,
                        font_size=text_line.font_size,
                        is_bold=text_line.is_bold,
                        is_block_start=(index == 0),
                    )
                )
        return lines
