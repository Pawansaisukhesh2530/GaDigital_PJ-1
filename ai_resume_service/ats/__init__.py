"""
ats — layout-aware resume parsing pipeline.

This package reconstructs a resume's *logical* document structure from PDF
geometry before grouping text into sections, so it does not depend on the
(unreliable) storage order in which PDF libraries return text.

Pipeline stages:
    1. DocumentAnalyzer      extract text blocks with coordinates + font info
    2. ColumnDetector        estimate column layout from block geometry
    3. ReadingOrderBuilder   reconstruct logical reading order
    4. LayoutHeadingDetector detect headings (text + font + position signals)
    5. HeadingNormalizer     map heading text -> canonical key (reused config)
    6. SectionBuilder        build layout-aware section boundaries
    7. PersonalInfoExtractor extract the resume header (name/contact) fields
    8. SectionValidator      validate sections + assign confidence
    9. AtsResumeParser       assemble the structured sections.json
   10. debug artifacts       document_layout / ordered_document / etc.
"""
