# Uploads

The secure file-upload helper is `cpvia_apply_store_upload()` in
`../helpers/apply_helpers.php`. It performs, on the **server**:

- upload error + `is_uploaded_file()` checks
- size limit (`CPVIA_APPLY_MAX_UPLOAD`, default 5 MB)
- extension allowlist (pdf/doc/docx) — final extension only, blocking
  double-extension attacks such as `resume.pdf.php`
- real MIME verification via `finfo` against a per-extension allowlist
- safe, non-guessable generated filenames (never the candidate's filename)
- returns metadata (original name, stored name, path, mime, size)

At runtime the destination is a writable directory you pass in (the live app
uses `uploads/resumes/`). Create that directory in the target project and make
it writable by the web server. Do **not** copy real uploaded resumes here.
