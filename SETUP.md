# CPVIA — Complete Setup Guide

This guide takes a brand-new machine from nothing to a fully running CPVIA
recruitment site with local Resume Intelligence auto-fill.

---

## 1. System requirements

| Component | Minimum | Recommended | Tested |
|-----------|---------|-------------|--------|
| OS | Windows 10 | Windows 11 | Windows 11 |
| Python | 3.11 | 3.12 | 3.12.13 |
| PHP | 8.0 | 8.2 | 8.2.12 (XAMPP) |
| Ollama | 0.30 | latest | 0.32.1 |
| Model | `qwen2.5:3b` | `qwen2.5:3b` | `qwen2.5:3b` (~1.9 GB) |
| RAM | 8 GB | 16 GB | — |
| CPU | 4 cores | 8 cores | — |
| Disk | 4 GB free | 6 GB free | — |

Required PHP extensions (all bundled with XAMPP): `curl`, `fileinfo`,
`pdo_sqlite`, `mbstring`.

---

## 2. Dependencies

### 2.1 Install Python 3.12
Download from https://www.python.org/downloads/ and enable "Add to PATH".
Verify: `python --version`.

### 2.2 Install PHP 8.2
Easiest on Windows: install **XAMPP** (https://www.apachefriends.org). PHP will
be at `C:\xampp\php\php.exe`. Verify: `C:\xampp\php\php.exe --version`.

### 2.3 Install Ollama
Download from https://ollama.com/download. After install, Ollama runs as a
background service on `http://localhost:11434`. Verify: `ollama --version`.

### 2.4 Pull the Qwen model
```powershell
ollama pull qwen2.5:3b
ollama list        # confirm qwen2.5:3b appears
```

### 2.5 Python packages
From `ai_resume_service/`:
```powershell
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
```
`requirements.txt` pins: fastapi, uvicorn, python-multipart, PyMuPDF,
pdfplumber, python-docx, Pillow, python-dotenv.

> This repo's `.venv` was created with `uv`; plain `python -m venv` + `pip`
> works identically. If you use `uv`: `uv venv && uv pip install -r requirements.txt`.

---

## 3. Project structure

```
cpvia/                          # PHP website (repo root)
├── index.php                   # Home
├── about.php careers.php contact.php expertise.php services.php ...  # public pages
├── router.php                  # dev-server router (maps /clean-url -> file.php)
├── .htaccess                   # Apache clean-URL rules (used under XAMPP)
├── header.php footer.php       # shared layout
├── db.php                      # SQLite connection helper
├── apply.php                   # 7-step candidate application wizard
├── apply_helpers.php           # validation + upload helpers for the wizard
├── resume_parse.php            # AI endpoint: forwards resume to FastAPI (CSRF, validation)
├── resume_ai_helper.php        # reusable config + cURL bridge to FastAPI
├── assets/
│   ├── CSS/  (apply_wizard.css, apply_resume_ai.css, style.css, ...)
│   ├── js/   (apply_wizard.js, apply_resume_ai.js)
│   └── images/
├── admin/                      # admin dashboard (login, jobs, applications, ...)
│   └── cpvia_database.sqlite   # SQLite recruitment database
├── uploads/resumes/            # stored candidate resumes (final submission)
├── sessions/                   # PHP session files
│
└── ai_resume_service/          # Python Resume Intelligence engine
    ├── main.py                 # CLI: PDF -> sections.json
    ├── run_intelligence.py     # CLI: sections.json -> structured_resume.json
    ├── config.py               # CLI paths/config
    ├── requirements.txt
    ├── config/llm_config.json  # model, base_url, temperature, keep_alive, timeouts
    ├── api/                    # FastAPI service
    │   ├── app.py              #   app factory + CORS + error handler
    │   ├── settings.py         #   env-based API settings
    │   ├── routes/resume.py    #   POST /api/resume/parse, GET /health
    │   ├── services/           #   orchestration + ollama health
    │   └── utils/              #   file security + output sanity
    ├── extractors/             # PDF (PyMuPDF/pdfplumber) + DOCX extraction
    ├── normalization/          # text normalizer
    ├── ats/                    # layout-aware ATS parser (columns, reading order, sections)
    ├── section_detection/      # heading keywords + normalizer (used by ATS)
    ├── llm/                    # qwen_client, prompt_builder, resume_parser, merger, post-process
    ├── prompts/                # one prompt template per section
    ├── schemas/                # resume schema, validators, confidence
    ├── utils/                  # file detection, logger
    ├── tests/                  # sample resumes (git-ignored; may contain PII)
    ├── output/                 # CLI output (git-ignored, regenerated)
    ├── runtime/                # per-request API workspaces (auto-created, auto-cleaned)
    └── temp/                   # scratch (git-ignored)
```

---

## 4. Installation (step by step)

```powershell
# 1. Get the code
git clone <repo-url> cpvia
cd cpvia

# 2. Python environment
cd ai_resume_service
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
cd ..

# 3. Ollama model
ollama pull qwen2.5:3b

# 4. Verify the database exists (ships with the repo)
#    admin/cpvia_database.sqlite  — no migration needed to run.
#    (If starting fresh, init_db.php / migrate_recruitment_db.php can build it.)

# 5. (Optional) configuration
#    Copy ai_resume_service/.env.example to .env to override defaults.
```

### Configuration reference (`ai_resume_service/config/llm_config.json`)
```json
{
  "model": "qwen2.5:3b",
  "base_url": "http://localhost:11434",
  "temperature": 0.0,
  "top_p": 0.9,
  "num_ctx": 4096,
  "timeout_seconds": 120,
  "max_retries": 3,
  "keep_alive": "30m"
}
```

### Environment variables (optional overrides)
| Variable | Default | Used by |
|----------|---------|---------|
| `RESUME_AI_BASE_URL` | `http://127.0.0.1:8000` | PHP → FastAPI URL |
| `RESUME_AI_TIMEOUT` | `300` | PHP cURL wait (seconds) |
| `OLLAMA_BASE_URL` | `http://localhost:11434` | engine + API |
| `OLLAMA_MODEL` | `qwen2.5:3b` | API |
| `OLLAMA_KEEP_ALIVE` | `30m` | keep model warm |
| `ALLOWED_ORIGINS` | local origins | API CORS |
| `MAX_UPLOAD_SIZE_MB` | `10` (API) / 5 (wizard) | upload limits |
| `HOST` / `PORT` | `127.0.0.1` / `8000` | API bind |

---

## 5. Running the project

Start in this order (each in its own terminal):

```powershell
# 1) Ollama — usually already running as a service. If not:
ollama serve

# 2) Backend (FastAPI) — from ai_resume_service/
cd ai_resume_service
.venv\Scripts\python.exe -m uvicorn api.app:app --host 127.0.0.1 --port 8000

# 3) Frontend (PHP) — from the project root (cpvia/)
cd ..
$env:RESUME_AI_BASE_URL='http://127.0.0.1:8000'
C:\xampp\php\php.exe -d display_errors=0 -d display_startup_errors=0 -d log_errors=1 -S 127.0.0.1:8090 router.php

# 4) Open the browser
start http://127.0.0.1:8090/
```

> **Always pass `router.php`** to `php -S`. Without it, every clean URL returns
> the home page. Under real Apache/XAMPP, drop it into the docroot and let
> `.htaccess` handle routing.

---

## 6. Verify installation

```powershell
# FastAPI + Ollama + model
Invoke-RestMethod http://127.0.0.1:8000/health
# → {"status":"ok","parser":true,"ollama":true,"model":"qwen2.5:3b"}

# Website pages
Invoke-WebRequest http://127.0.0.1:8090/           -UseBasicParsing   # Home 200
Invoke-WebRequest http://127.0.0.1:8090/careers    -UseBasicParsing   # Careers 200 (DB jobs)
Invoke-WebRequest "http://127.0.0.1:8090/apply?job_id=6" -UseBasicParsing  # Apply 200
Invoke-WebRequest http://127.0.0.1:8090/admin/login -UseBasicParsing  # Admin 200

# Ollama model present
ollama list
```

CLI-only pipeline test (no web server needed):
```powershell
cd ai_resume_service
.venv\Scripts\python.exe main.py "tests\Sukhesh Resume.pdf"   # -> output\sections.json
.venv\Scripts\python.exe run_intelligence.py                  # -> output\structured_resume.json
```

---

## 7. How to use (candidate flow)

1. Open **http://127.0.0.1:8090/careers**.
2. Choose a job → **Apply**.
3. **Step 1 — Resume:** upload PDF/DOC/DOCX → click **Analyze Resume &
   Pre-fill** → wait ~1–2 min (spinner shows progress messages).
4. On success the form pre-fills Personal, Professional, Education, and matching
   Skills. Fields the AI filled show a small "Filled from resume" tag.
5. Review/edit. Use **Clear auto-filled data** to undo the AI fill. Anything you
   typed yourself is never overwritten.
6. Finish the remaining steps, tick the declaration + consent (never auto-set),
   and **Submit**.

---

## 8. Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| **Every URL shows the home page** | `php -S` started without `router.php` | Restart with `php -S 127.0.0.1:8090 router.php` |
| **Port already in use** (`10048`) | Another process on 8000/8090 | Find & stop it: `Get-NetTCPConnection -LocalPort 8000 -State Listen` → `Stop-Process -Id <PID>`; or start on another port and set `RESUME_AI_BASE_URL` to match |
| **`/health` shows `"ollama":false`** | Ollama down or model missing | `ollama serve`; `ollama pull qwen2.5:3b`; `ollama list` |
| **Auto-fill returns "AI service unavailable"** | FastAPI not running / wrong URL | Start FastAPI on 8000; ensure PHP's `RESUME_AI_BASE_URL` matches |
| **`PHP Fatal error: Maximum execution time`** | Old build / very long parse | Fixed in current build (300s cURL, 320s PHP). Ensure you're on the latest code |
| **Analysis "took too long"** | Very dense resume > timeout | Increase `RESUME_AI_TIMEOUT` (e.g. `$env:RESUME_AI_TIMEOUT='420'`) before starting PHP |
| **"Only PDF, DOC and DOCX…"** | Unsupported/renamed file | Upload a real PDF/DOC/DOCX (magic-byte checked) |
| **"could not read any text"** | Scanned/image-only PDF | Use a digital PDF (OCR not enabled in this build) |
| **Database is locked** | Concurrent SQLite writers | Retry; ensure only one PHP server; don't open the .sqlite in another writer |
| **`ModuleNotFoundError`** | venv not used / deps missing | Run via `.venv\Scripts\python.exe`; re-run `pip install -r requirements.txt` |
| **CORS blocked (if calling API from a browser)** | origin not allowed | The wizard calls PHP (same-origin), not the API directly. For direct calls set `ALLOWED_ORIGINS` |

Server-side logs: FastAPI logs to its terminal (per-section timing, request ids).
PHP `resume_parse.php` logs via `error_log` (`[resume_parse] …`). No resume
content or PII is logged.

---

## 9. Command reference

```powershell
# --- Install ---
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
ollama pull qwen2.5:3b

# --- Run ---
ollama serve                                                   # if not already a service
.venv\Scripts\python.exe -m uvicorn api.app:app --host 127.0.0.1 --port 8000
$env:RESUME_AI_BASE_URL='http://127.0.0.1:8000'; C:\xampp\php\php.exe -S 127.0.0.1:8090 router.php

# --- Health / verify ---
Invoke-RestMethod http://127.0.0.1:8000/health
ollama list

# --- Stop (free project ports) ---
foreach ($p in 8000,8090) { Get-NetTCPConnection -LocalPort $p -State Listen -EA SilentlyContinue |
  ForEach-Object { Stop-Process -Id $_.OwningProcess -Force } }

# --- Clean runtime (safe; regenerates) ---
Remove-Item ai_resume_service\runtime -Recurse -Force -EA SilentlyContinue
Get-ChildItem ai_resume_service -Recurse -Directory -Filter __pycache__ |
  Where-Object { $_.FullName -notmatch '\\\.venv\\' } | Remove-Item -Recurse -Force

# --- CLI pipeline (no web) ---
.venv\Scripts\python.exe main.py "tests\Sukhesh Resume.pdf"
.venv\Scripts\python.exe run_intelligence.py
```

---

## 10. Verification checklist

- [ ] `ollama --version` works and `ollama list` shows `qwen2.5:3b`
- [ ] `Invoke-RestMethod http://127.0.0.1:8000/health` → `status: ok`, `ollama: true`
- [ ] Home `/` returns 200
- [ ] `/careers` returns 200 and renders DB jobs
- [ ] `/apply?job_id=<active id>` returns 200 with the wizard + Analyze button
- [ ] `/admin/login` returns 200
- [ ] Uploading a resume and clicking Analyze pre-fills the form within ~1–2 min
- [ ] "Clear auto-filled data" removes only AI-filled values
- [ ] Manual application still works with FastAPI stopped
