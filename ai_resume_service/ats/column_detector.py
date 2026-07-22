"""
column_detector.py

Stage 2: estimate the column layout of each page from block geometry.

Real resumes are frequently *mixed*: a single-column banner on top, a
two-column body, and maybe a three-column skills/education strip lower down.
A single page-wide column count cannot describe that, so detection works in
two levels:

    1. Band segmentation — walk blocks top to bottom and cut the page into
       horizontal *bands* wherever the run switches between full-width blocks
       (headers, section paragraphs that span the page) and narrower blocks.
       Each band is a region with a consistent column structure.
    2. Per-band column detection — within a band, cluster blocks by their left
       edge (x0). Columns in resumes align on their left edge, so x0 clustering
       is robust even when the gutter between columns is narrow (where a
       whitespace-gap test would fail). A split is only accepted when the
       resulting columns actually overlap vertically (proving they sit side by
       side rather than stacking).

Every threshold is relative to the page's own content width. Assigns
``block.band`` and ``block.column`` in place and returns one
:class:`PageLayout` per page describing the richest band found.
"""

from __future__ import annotations

from ats.models import Block, PageLayout
from ats.settings import COLUMN_SETTINGS


class ColumnDetector:
    """Detects bands + columns and tags each block with its band/column."""

    def __init__(self) -> None:
        self._cfg = COLUMN_SETTINGS

    def detect(self, blocks: list[Block], page_widths: list[float]) -> list[PageLayout]:
        """Detect layout for every page; set ``block.band``/``block.column``."""
        layouts: list[PageLayout] = []
        by_page: dict[int, list[Block]] = {}
        for block in blocks:
            by_page.setdefault(block.page, []).append(block)

        for page in sorted(by_page):
            page_width = page_widths[page - 1] if page - 1 < len(page_widths) else 0.0
            layouts.append(self._detect_page(page, by_page[page], page_width))
        return layouts

    def _detect_page(self, page: int, blocks: list[Block], page_width: float) -> PageLayout:
        content_x0 = min(b.x0 for b in blocks)
        content_x1 = max(b.x1 for b in blocks)
        content_width = max(content_x1 - content_x0, 1e-6)

        bands = self._segment_bands(blocks, content_width)

        # Tally blocks per column-count so the page label reflects the dominant
        # structure, not a single over-split band.
        blocks_per_count: dict[int, int] = {}
        bounds_per_count: dict[int, list[tuple[float, float]]] = {}

        for band_index, band_blocks in enumerate(bands):
            bounds = self._detect_band_columns(band_blocks, content_width)
            for b in band_blocks:
                b.band = band_index
                b.column = self._assign_column(b.x0, bounds)
            # Keep only columns that actually received blocks and renumber them
            # contiguously, so the column count reflects real occupied columns.
            bounds = self._prune_empty_columns(band_blocks, bounds)
            cols = len(bounds)
            blocks_per_count[cols] = blocks_per_count.get(cols, 0) + len(band_blocks)
            if cols not in bounds_per_count:
                bounds_per_count[cols] = bounds

        dominant_cols = max(blocks_per_count, key=lambda c: (blocks_per_count[c], c))
        best_bounds = bounds_per_count.get(dominant_cols, [(content_x0, content_x1)])
        widest_gutter = self._widest_gutter(best_bounds)

        confidence = self._layout_confidence(widest_gutter, content_width, dominant_cols)
        return PageLayout(
            page=page,
            column_count=dominant_cols,
            gutters=self._gutters_from_bounds(best_bounds),
            content_width=content_width,
            column_bounds=best_bounds,
            confidence=confidence,
        )

    # -- Step 1: band segmentation -----------------------------------------

    def _segment_bands(self, blocks: list[Block], content_width: float) -> list[list[Block]]:
        """Split blocks into horizontal bands.

        A new band starts when the run switches between full-width and narrow
        blocks, or when a large vertical gap separates one stacked region from
        the next (so distinct regions do not merge just because their lines
        happen to be narrow).
        """
        fullwidth_cut = content_width * self._cfg["fullwidth_ratio"]
        gap_cut = self._median_height(blocks) * self._cfg["band_gap_factor"]
        ordered = sorted(blocks, key=lambda b: (round(b.y0, 1), round(b.x0, 1)))

        bands: list[list[Block]] = []
        current: list[Block] = []
        current_wide: bool | None = None
        band_bottom: float | None = None

        for block in ordered:
            is_wide = block.width >= fullwidth_cut
            big_gap = band_bottom is not None and (block.y0 - band_bottom) > gap_cut
            if current and (is_wide != current_wide or big_gap):
                bands.append(current)
                current = []
                band_bottom = None
            current.append(block)
            current_wide = is_wide
            band_bottom = block.y1 if band_bottom is None else max(band_bottom, block.y1)

        if current:
            bands.append(current)
        return bands

    @staticmethod
    def _median_height(blocks: list[Block]) -> float:
        heights = sorted(b.height for b in blocks if b.height > 0)
        if not heights:
            return 1.0
        return heights[len(heights) // 2]

    # -- Step 2: per-band column detection ---------------------------------

    def _detect_band_columns(
        self, band_blocks: list[Block], content_width: float
    ) -> list[tuple[float, float]]:
        """Cluster a band's blocks by left edge into column bounds."""
        band_x0 = min(b.x0 for b in band_blocks)
        band_x1 = max(b.x1 for b in band_blocks)

        if len(band_blocks) < self._cfg["min_blocks_for_columns"]:
            return [(band_x0, band_x1)]

        clusters = self._cluster_by_x0(band_blocks, content_width)
        if len(clusters) < 2 or not self._columns_overlap(clusters):
            return [(band_x0, band_x1)]

        bounds: list[tuple[float, float]] = []
        for cluster in clusters:
            left = min(b.x0 for b in cluster)
            right = max(b.x1 for b in cluster)
            bounds.append((left, right))
        return bounds

    def _cluster_by_x0(
        self, band_blocks: list[Block], content_width: float
    ) -> list[list[Block]]:
        """Group blocks whose left edges are within a gutter-width of each other."""
        threshold = content_width * self._cfg["gutter_min_ratio"]
        ordered = sorted(band_blocks, key=lambda b: b.x0)

        clusters: list[list[Block]] = [[ordered[0]]]
        for block in ordered[1:]:
            last_x0 = max(b.x0 for b in clusters[-1])
            if block.x0 - last_x0 > threshold:
                clusters.append([block])
            else:
                clusters[-1].append(block)

        # A real column needs more than a single stray (indented) line.
        return [c for c in clusters if len(c) >= 2]

    @staticmethod
    def _columns_overlap(clusters: list[list[Block]]) -> bool:
        """True if the left-most and right-most clusters share vertical space."""
        if len(clusters) < 2:
            return False
        left, right = clusters[0], clusters[-1]
        left_y = (min(b.y0 for b in left), max(b.y1 for b in left))
        right_y = (min(b.y0 for b in right), max(b.y1 for b in right))
        overlap = min(left_y[1], right_y[1]) - max(left_y[0], right_y[0])
        span = max(left_y[1], right_y[1]) - min(left_y[0], right_y[0])
        return span > 0 and (overlap / span) >= 0.2

    # -- shared helpers -----------------------------------------------------

    @staticmethod
    def _prune_empty_columns(
        band_blocks: list[Block], bounds: list[tuple[float, float]]
    ) -> list[tuple[float, float]]:
        """Drop columns with no blocks and renumber block columns 0..k-1."""
        used = sorted({b.column for b in band_blocks})
        remap = {old: new for new, old in enumerate(used)}
        for b in band_blocks:
            b.column = remap[b.column]
        return [bounds[i] for i in used] if len(used) <= len(bounds) else bounds

    @staticmethod
    def _assign_column(x0: float, bounds: list[tuple[float, float]]) -> int:
        for index, (left, right) in enumerate(bounds):
            if left - 1e-6 <= x0 <= right + 1e-6:
                return index
        return min(
            range(len(bounds)),
            key=lambda i: min(abs(x0 - bounds[i][0]), abs(x0 - bounds[i][1])),
        )

    @staticmethod
    def _gutters_from_bounds(bounds: list[tuple[float, float]]) -> list[tuple[float, float]]:
        return [(bounds[i][1], bounds[i + 1][0]) for i in range(len(bounds) - 1)]

    @staticmethod
    def _widest_gutter(bounds: list[tuple[float, float]]) -> float:
        gaps = [bounds[i + 1][0] - bounds[i][1] for i in range(len(bounds) - 1)]
        return max(gaps, default=0.0)

    @staticmethod
    def _layout_confidence(widest_gutter: float, content_width: float, columns: int) -> float:
        if columns == 1 or widest_gutter <= 0:
            return 0.85
        ratio = widest_gutter / content_width
        return round(min(0.99, 0.6 + ratio * 2.0), 2)
