# AI Resume Service — Milestone 1 + 2: Text Extraction, Normalization & Section Detection

## Purpose

This service is the foundation of the future **AI Resume Auto-Fill** feature for
the CPVIA Recruitment Management System.

- **Milestone 1** turns a resume file (PDF or DOCX) into plain text, saved to disk
  and printed to the terminal.
- **Milestone 2** (this update) cleans that text and groups it into logical resume
  sections (summary, experience, education, skills, etc.) — still **without**
  extracting structured fields. That is reserved for the LLM-based parsing
  milestone that follows.

No AI, no OCR, no field-level JSON extraction, no API, and no connection to the
PHP application or its database exist yet. Those are future milestones (see
[Roadmap](#future-roadmap)).

This module is a **standalone Python project**. It does not modify, import from,
or get imported by the CPVIA PHP application in any way.

---

## Folder Structure

```
ai_resume_service/
├── main.py                    # CLI entry point
├── config.py                  # Paths & settings (env-driven)
├── requirements.txt           # Milestone 1 dependencies only
├── README.md                  # This file
├── .gitignore                 # Ignores venv, temp/output artifacts, .env
├── .env.example                # Example environment configuration
│
├── extractors/
│   ├── __init__.py
│   ├── base_extractor.py      # Abstract base class (extension point for OCR etc.)
│   ├── pdf_extractor.py       # PyMuPDF + pdfplumber fallback text extraction
│   ├── docx_extractor.py      # python-docx paragraph extraction
│   └── extractor_factory.py   # Detects file type -> returns correct extractor
│
├── normalization/
│   ├── __init__.py
│   └── text_normalizer.py      # TextNormalizer: cleans extracted text (Milestone 2)
│
├── section_detection/
│   ├── __init__.py
│   ├── section_detector.py     # ResumeSectionDetector: groups text by heading
│   └── section_keywords.py     # Canonical section keys + heading synonyms
│
├── utils/
│   ├── __init__.py
│   ├── logger.py               # Simple, readable console logger
│   └── file_detector.py        # File type detection (extension + magic bytes)
│
├── tests/                      # Place sample resumes here for manual CLI testing
├── temp/                       # Scratch space for future intermediate artifacts
└── output/                     # resume.txt, normalized_resume.txt, sections.json land here
```

---

## Installation

Requires Python 3.11 or 3.12.

```powershell
cd ai_resume_service

# Create the virtual environment
py -3.12 -m venv .venv
# or, if you use uv:
# uv venv --python 3.12 .venv

# Activate it (PowerShell)
.venv\Scripts\Activate.ps1

# Install dependencies
pip install -r requirements.txt

# Optional: create your local .env
copy .env.example .env
```

## Running the Project

```powershell
python main.py tests/resume.pdf
python main.py tests/resume.docx
```

Expected console output:

```
[INFO] Starting resume text extraction
[INFO] Processing: tests/resume.pdf
[INFO] Detected file type: PDF
[INFO] Extraction started
[INFO] Pages found: 2
[INFO] Extraction completed successfully
[INFO] Time taken: 0.18s
[INFO] Output saved: output/resume.txt
[INFO] Normalization started
[INFO] Normalization finished
[INFO] Processing time: 0.00s
[INFO] Output saved: output/normalized_resume.txt
[INFO] Section detection started
[INFO] Section detection finished
[INFO] Sections detected: summary, experience, education, skills
[INFO] Processing time: 0.00s
[INFO] Output saved: output/sections.json
----- Extracted Text Preview -----
John Doe
Senior Statistical Programmer
...
```

Each stage writes its own file so it can be inspected independently:

| File | Produced by |
|------|-------------|
| `output/<filename>.txt` | Milestone 1 — raw extracted text |
| `output/normalized_resume.txt` | Milestone 2 — cleaned text |
| `output/sections.json` | Milestone 2 — text grouped by resume section |

The raw extracted text is also printed to the terminal, unchanged from Milestone 1.

## Supported Formats (Milestone 1)

| Format | Status |
|--------|--------|
| PDF (text-based) | Supported |
| DOCX | Supported |
| PDF (scanned / image-only) | Detected, but returns a message stating OCR is required in a future milestone |
| DOC (legacy binary Word) | Detected, not yet supported — placeholder error |
| PNG / JPEG | Detected, not yet supported — reserved for future OCR milestone |

## Text Normalization (Milestone 2)

`normalization/text_normalizer.py` implements `TextNormalizer`, a pure text-cleanup
step. It never rewrites, summarizes, reorders, or removes resume content — it only
fixes formatting noise:

- Collapses excessive blank lines (3+ newlines -> 1 blank line) and repeated spaces/tabs.
- Converts tabs to spaces and strips leading/trailing whitespace per line.
- Normalizes Unicode to NFKC and removes invisible/zero-width characters (BOM,
  zero-width space/joiner, LTR/RTL marks) and PDF artifacts (soft hyphens, stray
  control characters).
- Normalizes curly quotes (`" " ' '`) to straight ASCII quotes, and en/em dashes to a
  plain hyphen.
- Normalizes varied bullet glyphs (`• ‣ ◦ ▪ ●` etc.) at the start of a line to `-`.
- Preserves paragraph structure, line order, and headings exactly — no line is ever
  reordered or dropped.
- **Email cleanup**: `john @ gmail .com` -> `john@gmail.com`.
- **Phone cleanup**: `+91 98765 43210` -> `+91 9876543210` (only for numbers written
  with a leading `+<country code>`; see [Known Limitations](#known-limitations)).
- **Date cleanup**: `January 2024`, `Jan. 2024`, and `01/2024` are all normalized to
  `Jan 2024`. Years, day numbers, ranges, and "Present" are left untouched.

## Section Detection (Milestone 2)

`section_detection/section_detector.py` implements `ResumeSectionDetector`, which
groups normalized text under canonical section keys. This is **grouping, not
parsing** — no individual fields (name, email, job title, etc.) are extracted.

A line is treated as a heading if it is short (5 words or fewer), does not end in
sentence punctuation, and matches a known heading variation (case/punctuation/spacing
insensitive) from `section_detection/section_keywords.py`. Everything until the next
recognized heading is grouped under that section.

### Supported section headings

| Canonical key | Recognized heading variations |
|---|---|
| `personal_information` | Personal Details, Personal Information, Contact, Contact Information/Details |
| `summary` | Profile, Summary, Professional Summary, Objective, Career Objective, About Me |
| `experience` | Work Experience, Experience, Employment (History), Professional Experience, Work/Career History |
| `education` | Education, Academic Details/Background, Educational Background, Qualifications |
| `skills` | Skills, Technical Skills, Core/Key Skills, Skills and Expertise, Areas of Expertise |
| `projects` | Projects, Key Projects, Academic/Personal Projects |
| `certifications` | Certifications, Certificates, Licenses and Certifications |
| `achievements` | Achievements, Awards, Awards and Activities, Honors and Awards, Accomplishments |
| `languages` | Languages, Language Proficiency |
| `interests` | Interests, Hobbies, Hobbies and Interests |
| `volunteering` | Volunteering, Volunteer Experience, Community Service |
| `publications` | Publications, Research Publications |
| `references` | Reference(s), Referee(s) |
| `others` | Text before the first recognized heading, and any unrecognized heading's content |

### Output format (`output/sections.json`)

```json
{
  "personal_information": "...",
  "summary": "...",
  "experience": "...",
  "education": "...",
  "skills": "...",
  "projects": "...",
  "certifications": "...",
  "achievements": "...",
  "languages": "...",
  "interests": "...",
  "volunteering": "...",
  "publications": "...",
  "references": "...",
  "others": "..."
}
```

All canonical keys are always present, even when empty, so downstream code (the
future LLM-parsing milestone) can rely on a stable schema.

## Error Handling

Both `TextNormalizer.normalize()` and `ResumeSectionDetector.detect()` are designed
to **never raise**:

- **Empty resume / empty input** — returns empty text / all-empty sections with a
  warning, no exception.
- **Missing headings** (e.g. a resume with no recognizable section titles at all) —
  all text is placed under `others`, with a warning logged.
- **Unknown/unusual headings** — a heading that doesn't match any known variation is
  treated as regular body text (grouped into whichever section is currently active,
  or `others` if none yet).
- **Duplicate headings** (e.g. two "EDUCATION" sections) — content is appended to the
  existing section instead of overwriting it; a warning is logged.
- **Empty sections** (heading found but no body text followed it) — kept as an empty
  string in the output, with a warning logged.
- **Unexpected/unsupported formatting** — any exception during normalization or
  detection is caught; the pipeline falls back to the original/raw text (placed
  under `others` for section detection) and logs a warning instead of crashing.

## Known Limitations

- No OCR — scanned/image-only PDFs will not yield text yet.
- No AI-based parsing or field extraction (name, email, skills, etc. are not pulled
  out individually — only grouped by section).
- Section detection is heading-based, not layout-based: resumes using pure visual
  separation (columns, font size/color only, no textual heading) may group some
  content under `others` or the wrong preceding heading, since plain-text extraction
  has already lost visual layout. This is inherent to Milestone 1's extraction and
  will be revisited if a future milestone adds layout-aware extraction.
- Phone normalization only handles the `+<country code> <digit groups>` pattern; purely
  local numbers or numbers with parentheses (e.g. `(555) 123-4567`) are intentionally
  left unchanged rather than guessed.
- No API layer (FastAPI is installed for a future milestone but no endpoints exist yet).
- No integration with the PHP application or the recruitment database.
- Single-file CLI usage only; no batch processing yet.

## Manual Testing Performed

Run against the sample resumes in `tests/` plus ad-hoc inline snippets covering the
cases called out below. All produced valid output with no crashes.

| Case | Resume used | Result |
|---|---|---|
| Single-page, modern two-column resume | `tests/two-column.pdf` | Headings (Education, Contact, Skills, Languages, Work Experience, Reference, Profile) detected correctly; body text grouped under the right section despite column reordering from extraction. |
| Single-page, traditional resume | `tests/simple.pdf` | Summary, Skills, Experience (heading present, body captured under Summary due to heading/body ordering in the source PDF), Education detected; heading-only sections logged as warnings. |
| Empty resume (empty string input) | inline test | Returns empty normalized text and all-empty `sections.json` with a warning, no exception. |
| Resume without any headings | inline test (plain paragraph, no section titles) | All text placed under `others`; warning logged: "No known section headings were detected". |
| Resume with unusual/unknown heading | inline test (`RANDOM HEADING` followed by a known `EDUCATION` heading) | Unknown heading's text placed under `others`; known heading grouped correctly. |
| Email/phone/date formatting fixes | inline test (`john @ gmail .com`, `+91 98765 43210`, `Jan. 2024`, `01/2024`) | All normalized to `john@gmail.com`, `+91 9876543210`, `Jan 2024`, `Jan 2024`. |
| Bullets, quotes, dashes, tabs, excess blank lines | inline test | Bullets -> `-`, curly quotes -> straight quotes, en/em dash -> `-`, tabs -> space, 4+ blank lines collapsed to 1. |
| Existing Milestone 1 output unaffected | `tests/simple.pdf`, `tests/two-column.pdf` | `output/simple.txt` and `output/two-column.txt` (raw extraction) are byte-identical to before this change; only new files were added. |

## Future Roadmap

1. **Milestone 3** — AI-based structured parsing: feed `output/sections.json` into an
   LLM (Qwen) to extract structured fields (name, email, phone, skills, experience,
   education, etc.) into JSON.
2. **Milestone 4** — FastAPI endpoint(s) exposing extraction + normalization +
   parsing as a service.
3. **Milestone 5** — Integration with the PHP Apply Wizard: `resume_parse.php`
   forwards the upload to this service and `assets/js/apply_resume_ai.js` maps
   the structured result into the wizard via the `window.CPVIAApplyWizard` API.
4. **Later** — OCR support for scanned PDFs and images (PaddleOCR/PaddlePaddle),
   if still needed once real-world usage data is available.

Each milestone builds on this foundation without needing to rewrite it. The
`ExtractorFactory`/`BaseExtractor` abstraction lets OCR-based extractors be added
without touching PDF/DOCX code, and `TextNormalizer`/`ResumeSectionDetector` are
plain, dependency-free classes that the next milestone can call directly:

```python
raw_text = extractor.extract().text
normalized = TextNormalizer().normalize(raw_text).text
sections = ResumeSectionDetector().detect(normalized).sections
# sections -> passed straight into the Milestone 3 LLM parsing step
```
