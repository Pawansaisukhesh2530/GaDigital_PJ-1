# Validators

Validation is implemented as plain PHP functions inside the shared helpers
(kept together so a module stays self-contained), not as separate classes:

- **Application validation** — `cpvia_apply_validate()` in `../helpers/apply_helpers.php`
  (server-side, full form validation with allowlists and length limits).
- **Job validation** — `cpvia_validate_job()` in `../helpers/job_helpers.php`
  (required fields, numeric ranges, job-code uniqueness).

The matching **client-side** validation lives in:
- `../js/apply_wizard.js` (per-step + full validation)
- `../js/job_wizard.js` (per-step + publish validation)

Client validation is for UX only; the server functions are the source of truth.
