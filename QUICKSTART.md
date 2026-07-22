# CPVIA — Quick Start (≈10 minutes)

Get the recruitment site + local resume auto-fill running from scratch.

## 0. Prerequisites (install once)
- **Python 3.12** — https://www.python.org/downloads/ (check "Add to PATH")
- **PHP 8.2** — via **XAMPP** https://www.apachefriends.org (PHP at `C:\xampp\php\php.exe`)
- **Ollama** — https://ollama.com/download (runs as a service on port 11434)

## 1. Get the model (~1.9 GB, one time)
```powershell
ollama pull qwen2.5:3b
```

## 2. Install Python dependencies (one time)
```powershell
cd cpvia\ai_resume_service
python -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
```

## 3. Start the two servers (two terminals)

**Terminal A — Backend (FastAPI):**
```powershell
cd cpvia\ai_resume_service
.venv\Scripts\python.exe -m uvicorn api.app:app --host 127.0.0.1 --port 8000
```

**Terminal B — Frontend (PHP):**
```powershell
cd cpvia
$env:RESUME_AI_BASE_URL='http://127.0.0.1:8000'
C:\xampp\php\php.exe -S 127.0.0.1:8090 router.php
```

## 4. Verify (30 seconds)
```powershell
Invoke-RestMethod http://127.0.0.1:8000/health
# expect: status=ok, ollama=true, model=qwen2.5:3b
```
Open **http://127.0.0.1:8090/**

## 5. Try it
1. **Careers** → pick a job → **Apply**
2. **Step 1:** upload a resume (PDF/DOC/DOCX) → **Analyze Resume & Pre-fill**
3. Wait ~1–2 min → review the pre-filled steps → **Submit**

Admin: **http://127.0.0.1:8090/admin/login**

---

### Gotchas
- **Must** pass `router.php` to `php -S`, or every URL shows the home page.
- If port 8000 is taken, use another port and set `RESUME_AI_BASE_URL` to it.
- If auto-fill says "unavailable", the FastAPI/Ollama service isn't up — the
  manual application still works regardless.

Full details: **SETUP.md** · Troubleshooting: **SETUP.md §8**
