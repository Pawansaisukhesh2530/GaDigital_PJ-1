# CPVIA Reusable Modules

A **reference copy** of the reusable Careers, Apply, and Jobs modules from the
CPVIA Recruitment Management System, packaged so they can be integrated into
another plain-PHP + SQLite project.

> ⚠️ This folder is **not** part of the live application. Nothing in the running
> CPVIA project includes or executes files from here. It is a packaged library
> to copy into a future project. It intentionally contains **no** database,
> uploaded files, credentials, sessions, or runtime data.

---

## Folder contents

| Folder | Contents | Module |
|--------|----------|--------|
| `careers/` | `careers.php` — public job listing page | Careers |
| `apply/` | `apply.php` (7-step wizard), `apply_helpers.php` | Apply |
| `jobs/` | `add_job.php`, `edit_job.php`, `job_helpers.php` (admin 8-step wizard) | Jobs |
| `components/` | `job_wizard_form.php` — shared Add/Edit wizard markup partial | Jobs |
| `helpers/` | `db.php` (PDO wrapper), `apply_helpers.php`, `job_helpers.php` | Shared |
| `validators/` | Notes: validation lives in the helper files (see README there) | Shared |
| `uploads/` | Notes: secure upload helper location (see README there) | Apply |
| `css/` | `apply_wizard.css` (public), `admin.css` (admin design system) | Shared |
| `js/` | `apply_wizard.js` (public wizard), `job_wizard.js` (admin wizard) | Shared |

---

## Required dependencies

- PHP 8.0+ with `pdo_sqlite` and `fileinfo` extensions.
- No Composer packages, no front-end build step, no CDN dependencies.
- The rich-text editor and wizards are self-contained vanilla JS.

---

## Required database tables

These modules expect the migrated recruitment schema. Minimum tables:

- `jobs` — job postings (legacy columns + expanded wizard columns).
- `applications` — candidate applications (legacy columns + expanded columns).
- `skills` — master skills (`id`, `name`, `is_active`).
- `job_skills` — `job_id`, `skill_id`, `skill_type` (`required`/`preferred`).
- `application_professional_details`, `application_education`,
  `application_documents`, `application_skills` — child tables (FK `application_id`).

Use the project's `migrate_recruitment_db.php` (or `init_db.php` for a fresh
install) as the reference schema. The database itself is **not** shipped here.

---

## Shared connection

All modules use a single shared PDO accessor:

```php
require_once 'helpers/db.php';
$pdo = cpvia_db('/absolute/path/to/database.sqlite'); // WAL + busy_timeout, reused
```

Never open a second PDO connection — reuse `cpvia_db()` to avoid SQLite locking.

---

## Integrating a module into another PHP project

### 1. Careers
- Copy `careers/careers.php`.
- It needs `helpers/db.php` and your site header/footer includes.
- Adjust the `$db_file` path and the `include header/footer` lines to your project.
- Lists jobs where `status IN ('Active','Published')`.

### 2. Apply (7-step wizard)
- Copy `apply/apply.php` + `helpers/apply_helpers.php`,
  `css/apply_wizard.css`, `js/apply_wizard.js`.
- Ensure `<link rel="stylesheet" href=".../apply_wizard.css">` and
  `<script src=".../apply_wizard.js"></script>` resolve to the copied assets.
- Create a writable `uploads/resumes/` directory and a writable `sessions/`
  directory (used for the CSRF token).
- Flow: Resume Upload → Personal → Professional → Education → Skills →
  Questions → Review. Writes across `applications` + child tables atomically.
- **Future AI auto-fill hook:** a resume parser can call
  `window.cpviaApplyAutofill({ full_name:'…', email:'…', skills:[1,2], … })`
  to populate later steps. No parsing is implemented yet.

### 3. Jobs (admin 8-step Add/Edit wizard)
- Copy `jobs/add_job.php`, `jobs/edit_job.php`, `helpers/job_helpers.php`,
  `components/job_wizard_form.php`, `css/admin.css`, `js/job_wizard.js`.
- Both pages include the shared `job_wizard_form.php` partial and the same JS/CSS,
  so Add and Edit stay identical.
- Requires your admin auth/layout partials — swap in your own
  `auth.php` + layout includes at the top of each page.

---

## Required CSS / JS per module

| Module | CSS | JS |
|--------|-----|-----|
| Careers | your site `style.css` | none required |
| Apply | `css/apply_wizard.css` (+ site tokens) | `js/apply_wizard.js` |
| Jobs | `css/admin.css` | `js/job_wizard.js` |

The CSS relies on brand CSS variables (`--primary-blue`, `--primary-orange`,
`--text-dark`, `--text-light`); `apply_wizard.css` provides fallbacks.

---

## What is intentionally excluded

SQLite database, uploaded resumes, admin authentication/session data, temporary
files, debug files, logs, caches, runtime data, and project-specific config.
