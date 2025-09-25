# CIS Modules – Developer Guide

This guide explains how to build module views that render inside the global CIS template, share assets/data, and keep pages fast and maintainable.

## Routing during development

Because .htaccess rewrites may be disabled in dev, always link using the query route:

- https://staff.vapeshed.co.nz/modules/module.php?module={module}&view={view}

The template resolves and renders `modules/{module}/views/{view}.php` (content-only view).

## Content-only views

Views must NOT include a full HTML shell. Use the shared helpers and let the template provide the outer chrome (header, footer, sidebar, breadcrumb):

```php
<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
tpl_shared_assets();
?>
<div class="container-fluid">
  <!-- your content here -->
  <!-- Avoid breadcrumbs here; the outer template renders them. -->
  <!-- Optionally include modal blocks at the bottom of the view. -->
  <?php
  if (function_exists('tpl_block') && tpl_block_exists(__FILE__, '../blocks/your_modal.php')) {
    tpl_block(__FILE__, '../blocks/your_modal.php');
  }
  ?>
</div>
```

## Per-view meta

Place a meta file alongside your view: `modules/{module}/views/{view}.meta.php` returning an array:

- title: string – page heading
- subtitle: string – small description
- breadcrumb: array – e.g. [ ['label'=>'Home','href'=>'https://staff.vapeshed.co.nz/'], ['label'=>'Module'], ['label'=>'View'] ]
- layout: one of `card` (default), `plain`, `grid-2`, `grid-3`, `split`, `centered`, `full-bleed`
- tabs: array of tabs [ {key,label,href,active?} ]
- active_tab: string – which tab is active
- page_title: string – browser tab title (env label auto-suffixed)
- meta_description, meta_keywords, noindex: head meta
- right: string (HTML) – renders actions on the right side of the breadcrumb
- hide_quick_search: bool – hides the quick-product-search widget
- suppress_breadcrumb: bool – suppresses breadcrumb entirely
- assets: [ 'css' => [string|[string,attrs]...], 'js' => [string|[string,attrs]...] ] – queued and rendered globally

Example:

```php
<?php
declare(strict_types=1);
return [
  'title' => 'Transfers',
  'subtitle' => 'Operations hub',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Transfers'],
    ['label' => 'Dashboard'],
  ],
  'layout' => 'plain',
  'tabs' => [ ['key'=>'dashboard','label'=>'Overview','href'=>'https://staff.vapeshed.co.nz/modules/module.php?module=transfers&view=dashboard','active'=>true] ],
  'right' => '<div class="btn-group">…</div>',
  'assets' => [
    'css' => ['https://staff.vapeshed.co.nz/modules/_shared/assets/css/cis-layouts.css'],
    'js'  => [ ['https://staff.vapeshed.co.nz/modules/_shared/assets/js/cis-shared.js', ['defer'=>true]] ],
  ],
];
```

## Quick actions in breadcrumb (right slot)

Use the `right` meta key to inject small action buttons. Keep markup minimal and safe (internal HTML only). Heavier UIs (modals, forms) should be placed into block partials under `modules/{module}/blocks/` and included from the view bottom.

Example in view:

```php
if (function_exists('tpl_block')) {
  if (tpl_block_exists(__FILE__, '../blocks/your_modal.php')) {
    tpl_block(__FILE__, '../blocks/your_modal.php');
  }
}
```

## Asset reuse

Use the shared asset helpers in views or blocks to queue assets globally:

```php
tpl_style('https://staff.vapeshed.co.nz/modules/your-module/assets/css/page.css');
tpl_script('https://staff.vapeshed.co.nz/modules/your-module/assets/js/page.js', ['defer'=>true]);
```

Alternatively declare them in meta under `assets`.

## Data sharing

Shared data (like outlet lists) should be provided via a shared endpoint or JSON under `modules/_shared/data/` and consumed by `cis-shared.js`. This avoids duplicating large option lists in multiple UIs.

- JS helper: `https://staff.vapeshed.co.nz/modules/_shared/assets/js/cis-shared.js`

In your modal block:

```php
if (function_exists('tpl_script')) {
  tpl_script('/modules/_shared/assets/js/cis-shared.js', ['defer'=>true]);
}
```

Then in JS:

```js
CIS.fetchOutlets().then(list => CIS.populateOutletSelects('#sel1', '#sel2'));
```

## Security

- Don’t print PII. Use CSRF and server-side permission checks in handlers.
- Keep all URLs absolute to staff domain.
- Never include secrets in the repo.

---

This document is intentionally concise. For examples, see the Transfers and Purchase Orders modules.

## Module Docs (Standard & Index)

Each module should keep a `docs/` folder with four files:
- `REFERENCE_MAP.md` – DOM IDs, CSS classes, JS entry points, AJAX routes
- `COMPONENT_INDEX.md` – reusable UI components with markup + hooks
- `KNOWLEDGE_BASE.md` – architecture, flows, conventions, perf/security notes
- `CHANGELOG.md` – dated changes

Central index of all module docs:
- https://staff.vapeshed.co.nz/modules/docs/INDEX.md

When you add/edit views, assets, or AJAX routes, update the module’s docs in the same change.
Modules Directory — Standardization and Template

This directory hosts all feature modules for https://staff.vapeshed.co.nz.

Use `MODULE_TEMPLATE/` as the canonical starting point for any new module. It includes:
- A minimal POST-only AJAX handler with CSRF + auth guards
- A shared module tools file (PDO, JSON envelopes, helpers)
- Views (user + admin) wired to the handler
- Assets (JS/CSS) stubs under 25KB, modular and scoped
- Idempotent schema migrator + starter SQL
- Clear TODOs and placeholders to rename safely

Quick start to create a new module (replace my-module with your slug):

1) Copy the template
   - From project root: `/public_html/modules/`
   - Command (mysecureshell friendly):

```
cp -r MODULE_TEMPLATE my-module
find my-module -type f -print0 | xargs -0 sed -i 's/__MODULE_SLUG__/my-module/g'
find my-module -type f -print0 | xargs -0 sed -i 's/__MODULE_NAME__/My Module/g'
```

2) Wire routes/paths
- Update links in your view(s) to point to https://staff.vapeshed.co.nz/modules/my-module/ajax/handler.php
- Keep POST-only calls with CSRF header `X-CSRF-Token` from the meta tag injected by `app.php`

3) Run schema
- Open https://staff.vapeshed.co.nz/modules/my-module/schema/migrate.php in a browser (DEV/Staging)
- Or run via CLI: `php modules/my-module/schema/migrate.php`

4) Check the admin stub
- https://staff.vapeshed.co.nz/modules/my-module/views/admin/dashboard.php

Security & Standards
- Always start PHP files with `declare(strict_types=1);` and include: `require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';`
- Never store secrets in code. All DB/session/bootstrap comes from app.php and config.
- AJAX must be POST-only, CSRF verified, and authenticated.
- Keep CSS/JS files under 25KB each and modular.
- Use prepared statements only; no string-concatenated SQL.

For details see: `MODULE_TEMPLATE/README.md`.

---

Rendering via CIS_TEMPLATE (Required)

All end-user/admin pages must render inside the standard CIS chrome (header, sidebar, footer).
Use the CIS template router with either of:

- Pretty route (requires .htaccess):
   - https://staff.vapeshed.co.nz/module/{module}/{view}
- Query route (dev-safe, no .htaccess):
  - https://staff.vapeshed.co.nz/modules/module.php?module={module}&view={view}

Entry points can also forward directly by setting $_GET and requiring the template:

```php
// modules/your-module/dashboard.php
declare(strict_types=1);
$_GET['module'] = 'your-module';
$_GET['view'] = 'dashboard';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/module.php';
```

Per-View Metadata (Title, Breadcrumb, Layout)

Place a meta file alongside your view: `modules/{module}/views/{view}.meta.php` returning an array:

```php
<?php
return [
   'title' => 'Human Title',
   'subtitle' => 'Optional subtitle',
   'breadcrumb' => [
      ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
  ['label' => 'Your Module', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=your-module&view=dashboard'],
      ['label' => 'This View'],
   ],
   // Layout variants for the inner content wrapper
   // Options: card (default), plain, grid-2, grid-3, split, centered, full-bleed
   'layout' => 'card',
      // Page-level head tags (optional)
      'page_title' => 'Human Title — CIS',
      'meta_description' => 'Short description for search and social.',
      'meta_keywords' => 'comma, separated, keywords',
      'noindex' => false,
];
```

Layout CSS is auto-included by CIS_TEMPLATE from:
`https://staff.vapeshed.co.nz/modules/_shared/assets/css/cis-layouts.css`

Important: Views that previously emitted their own breadcrumbs should only do so when
they are NOT being rendered by CIS_TEMPLATE. Use this guard to avoid duplicates:

```php
if (empty($GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'])) {
   tpl_breadcrumb([...]);
}
```
