# CPVIA — Recruitment System + Resume Intelligence

A PHP recruitment website (public careers site, 7-step application wizard, and
admin dashboard) integrated with a local **Resume Intelligence** service that
reads a candidate's resume and auto-fills the application form.

The AI runs **fully locally** (Ollama + Qwen 2.5 3B) — no resume data leaves the
machine.

---

## Table of contents
- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Running the project](#running-the-project)
- [Usage](#usage)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)
- [License & acknowledgements](#license--acknowledgements)

---

## Overview

Two cooperating parts:

| Part | Tech | Role |
|------|------|------|
| **CPVIA website** | PHP 8 + SQLite | Careers pages, 7-step Apply wizard, Admin dashboard |
| **Resume Intelligence** | Python 3.12 + FastAPI + Ollama (Qwen 2.5 3B) | Extracts a structured resume from an uploaded PDF/DOC/DOCX |

The website calls the Resume Intelligence service **server-side** (PHP cURL →
FastAPI). The AI result only **pre-fills** the application form; the candidate
reviews and edits everything, and the existing submission flow still owns
validation and database storage.

## Features

- Public careers site (Home, About, Expertise, Services, Contact, Careers)
- Dynamic job listings + details (SQLite)
- 7-step candidate application wizard with client + server validation, draft
  auto-save, and single-submit protection
- **Resume auto-fill** (optional enhancement):
  - Upload PDF / DOC / DOCX and "Analyze Resume & Pre-fill"
  - Fills only empty fields (never overwrites what the candidate typed)
  - Matches resume skills against the recruitment skills list
  - "Clear auto-filled data" to undo the pre-fill
  - Always degrades gracefully — if the AI is down, the manual application
    still works
- Admin dashboard: jobs management, applications management, applicant profiles
- Local, private AI (Ollama + Qwen 2.5 3B); isolated per-request workspaces;
  controlled JSON errors; coordinated long-request timeouts

## Architecture

```
Candidate uploads resume (Apply · Step 1)
        │
        ▼
  PHP endpoint  resume_parse.php   (same-origin, CSRF, file validation)
        │  cURL (timeout 300s)
        ▼
  FastAPI  POST /api/resume/parse   (isolated runtime/<uuid>/ workspace)
        │
        ▼
  Extraction        PyMuPDF → pdfplumber  (digital text)
        │           └─ if no text: detected as scanned → OCR fallback*
        ▼
  Normalization     (whitespace/unicode/bullets/dates)
        ▼
  Layout-Aware ATS Parser   → sections.json (in-memory)
        ▼
  Qwen 2.5 3B (Ollama)      7 section prompts (personal, summary, education,
        │                    experience, projects, skills, languages)
        ▼
  Deterministic post-process + Validation + Confidence
        ▼
  structured_resume.json   (returned as JSON)
        │
        ▼
  PHP normalizes → Browser JS maps → safe auto-fill of the wizard
```

\* **OCR note:** the pipeline *detects* scanned/image-only PDFs and returns a
clear "OCR needed" result, but PaddleOCR is **not installed/enabled** in this
build. All current extraction is digital-text only. Enabling OCR is a documented
future step (see `SETUP.md`).

## Requirements

| Component | Version (tested) | Notes |
|-----------|------------------|-------|
| OS | Windows 10/11 | Linux/macOS work with path adjustments |
| Python | 3.12.x | virtual env in `ai_resume_service/.venv` |
| PHP | 8.2.x | with `curl`, `fileinfo`, `pdo_sqlite` extensions (XAMPP includes them) |
| Ollama | 0.32.x | https://ollama.com |
| Model | `qwen2.5:3b` (~1.9 GB) | pulled via `ollama pull` |
| RAM | 8 GB min, 16 GB recommended | 3B model needs ~4 GB free during inference |
| CPU | 4+ cores recommended | generation is CPU-bound (~14 tok/s) |
| Disk | ~4 GB | model (1.9 GB) + venv + project |

> Performance: a resume takes roughly **60–180 seconds** to analyze on CPU
> (longer for very dense resumes). A GPU would speed this up dramatically.

## Installation

See **[SETUP.md](SETUP.md)** for the full step-by-step guide. Short version:

```powershell
# 1. Python deps (from ai_resume_service/)
cd ai_resume_service
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt

# 2. Ollama model
ollama pull qwen2.5:3b
```

PHP needs no package manager for this project (uses built-in extensions +
SQLite). Install PHP via XAMPP (Windows) or your OS package manager.

## Running the project

Open **three** terminals (Ollama usually already runs as a service):

```powershell
# Terminal 1 — Ollama (skip if the Ollama app/service is already running)
ollama serve

# Terminal 2 — Backend (FastAPI), from ai_resume_service/
.venv\Scripts\python.exe -m uvicorn api.app:app --host 127.0.0.1 --port 8000

# Terminal 3 — Frontend (PHP), from the project root (cpvia/)
$env:RESUME_AI_BASE_URL='http://127.0.0.1:8000'                                    
>> C:\xampp\php\php.exe -d display_errors=0 -d display_startup_errors=0 -d log_errors=1 -S 127.0.0.1:8090 router.php  
```

Then open: **http://127.0.0.1:8090/**

> The `router.php` argument is **required** when using PHP's built-in server so
> clean URLs (`/about`, `/careers`, …) resolve correctly. Under Apache/XAMPP the
> `.htaccess` handles this automatically.

## Usage

1. Go to **Careers** → pick a job → **Apply**.
2. **Step 1:** upload your resume (PDF/DOC/DOCX), click **Analyze Resume &
   Pre-fill**, wait ~1–2 minutes.
3. Review the pre-filled Personal / Professional / Education / Skills steps.
   Edit anything; use **Clear auto-filled data** to undo the AI fill.
4. Complete the remaining steps (Questions, Review), accept the declaration/
   consent, and **Submit**.

Admin dashboard: **http://127.0.0.1:8090/admin/login**

## Troubleshooting

See the [Troubleshooting](SETUP.md#troubleshooting) section in `SETUP.md` for
port conflicts, Ollama/model issues, timeouts, database locks, and more.

## Documentation

- **[QUICKSTART.md](QUICKSTART.md)** — run it in under 10 minutes
- **[SETUP.md](SETUP.md)** — full setup, structure, commands, troubleshooting
- **[ai_resume_service/README.md](ai_resume_service/README.md)** — engine internals

## License & acknowledgements

- Internal CPVIA project. Add a license here if distributing.
- Built with [Ollama](https://ollama.com), [Qwen 2.5](https://qwenlm.github.io),
  [FastAPI](https://fastapi.tiangolo.com), [PyMuPDF](https://pymupdf.readthedocs.io),
  [pdfplumber](https://github.com/jsvine/pdfplumber), and PHP + SQLite.
