MODULE_TEMPLATE — Bootstrap for New Modules

This template targets https://staff.vapeshed.co.nz and enforces CIS standards.

Replace placeholders:
- __MODULE_SLUG__ → your module folder name (kebab-case)
- __MODULE_NAME__ → human title

Structure
- ajax/
  - handler.php — POST-only router, dispatches actions
  - tools.php — shared helpers (JSON envelopes, PDO, CSRF, auth)
  - actions/ — add your action files here (one per action)
- assets/
  - js/module.js — module-specific JS
  - css/module.css — minimal CSS (<25KB)
- schema/
  - 001_core.sql — starter table(s)
  - migrate.php — idempotent migrator
- views/
  - index.php — end-user view (requires app.php)
  - admin/dashboard.php — admin stub

Rendering Inside CIS (Required)
- All pages must render inside the standard CIS template shell.
- Use either the pretty route (with .htaccess) or the dev-safe query form:
  - Pretty:  https://staff.vapeshed.co.nz/module/__MODULE_SLUG__/index
  - Query:   https://staff.vapeshed.co.nz/modules/CIS_TEMPLATE.php?module=__MODULE_SLUG__&view=index
- Entry scripts can forward into CIS_TEMPLATE by setting $_GET and requiring it.

Per-View Title, Breadcrumb, and Layout
- Create `views/{view}.meta.php` returning an array:
  - title, subtitle, breadcrumb (array of {label, href?}), and layout.
- Supported `layout` values: card (default), plain, grid-2, grid-3, split, centered, full-bleed.
- CIS template auto-loads `modules/_shared/assets/css/cis-layouts.css`.
- If your view renders a breadcrumb directly, guard it to avoid duplicates:
  ```php
  if (empty($GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'])) { tpl_breadcrumb([...]); }
  ```

Page Head (title/description/robots)
- In your `views/{view}.meta.php`, you can set head properties that the CIS template forwards to html-header:
  - `page_title` (string) — full document title; defaults to “{title} — CIS”
  - `meta_description` (string)
  - `meta_keywords` (string)
  - `noindex` (bool) — set to true to emit a noindex robots tag if supported by html-header

Conventions
- All PHP begins with `declare(strict_types=1);` and `require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';`
- AJAX is POST-only with CSRF header `X-CSRF-Token` and JSON reply `{ success, data|error, request_id }`.
- Use prepared statements only. No inline concatenated SQL.
- Keep assets under 25KB each; prefer modular JS/CSS.
- No secrets in code. Use app.php + config for bootstrap.

How to use
1) Copy this folder to modules/__MODULE_SLUG__
2) Search/replace placeholders globally
3) Implement actions under ajax/actions and wire them in ajax/handler.php route map
4) Run schema migrator via browser or CLI
5) Load views and test network calls in DevTools

Rollback & Backups
- Keep one backup per edited file under private_html/backups as per org policy.
- Migrator is idempotent; re-running is safe.

Docs (Required)
- For every new module, create `docs/` with:
  - `REFERENCE_MAP.md`, `COMPONENT_INDEX.md`, `KNOWLEDGE_BASE.md`, `CHANGELOG.md`
- Link them in the central index: https://staff.vapeshed.co.nz/modules/docs/INDEX.md
- Use absolute links under https://staff.vapeshed.co.nz in all documentation.
