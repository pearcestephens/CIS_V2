# Stock Transfers Dashboard – Component Index

This page catalogs the reusable UI pieces used on the Stock Transfers dashboard so you can confidently reuse them in other screens without re‑inventing markup or styles.

## Scope and naming

- Prefix: All dashboard‑scoped classes start with `stx-` to avoid collisions with global CSS.
- Tech: Bootstrap 4 utilities + minimal custom CSS in `modules/transfers/stock/assets/css/dashboard.css` and JS in `modules/transfers/stock/assets/js/dashboard.js`.
- Include: This page (`views/dashboard.php`) pulls CSS/JS via `tpl_style(...)` and `tpl_script(...)`. Reuse the same includes on other pages that use these components.

## Components

### 1) KPI card (stx-kpi)
- Purpose: Compact stat tiles with subtle periodic shine.
- Markup skeleton:
  <div class="card stx-kpi stx-kpi--open">
    <div class="card-body py-2">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted text-uppercase" style="font-size:12px">Open</div>
          <div class="d-flex align-items-baseline">
            <div class="stx-kpi-value">42</div>
            <div class="ml-2 text-muted" style="font-size:12px">Draft + Packing + Ready</div>
          </div>
        </div>
        <div class="stx-kpi-icon" aria-hidden="true"><i class="fa fa-wrench"></i></div>
      </div>
    </div>
  </div>
- Variants: `stx-kpi--open`, `stx-kpi--motion`, `stx-kpi--arrive`, `stx-kpi--closed`.
- Notes: Shine is triggered by JS; opt‑out by removing class `stx-kpi` or respecting `prefers-reduced-motion` if needed.

### 2) Scrollable table with sticky headers (stx-table)
- Purpose: Dense tables with fixed 500px scroll area and sticky headers.
- Usage: Wrap your `<table>` in `<div class="table-responsive stx-table"> ... </div>` and include a `thead`.
- Empty state: Use `<tr><td colspan="N"><div class="stx-empty">Message…</div></td></tr>`.

### 3) Typeahead input (stx-typeahead)
- Purpose: Lightweight, dependency‑free suggestions for outlets.
- Markup:
  <div class="form-group stx-typeahead">
    <label>From <span class="stx-chip"><span class="stx-chip-x" data-clear="stx-filter-from">×</span></span></label>
    <input id="stx-filter-from" class="form-control" autocomplete="off">
    <div id="stx-ta-from" class="stx-typeahead-menu" style="display:none"></div>
  </div>
- JS hook: `bindTypeahead(inputEl, menuEl)` from `dashboard.js`.
- Behavior: Double‑click/focus/ArrowDown to open; Enter selects; Esc closes. Suggestions are name‑only; IDs are available as tooltips.

### 4) Label clear chip (stx-chip)
- Purpose: Place a compact clear (×) control inside a label.
- Markup: `<span class="stx-chip"><span class="stx-chip-x" data-clear="target-input-id">×</span></span>`
- JS: Global click handler clears the input by ID and fires `change`.

### 5) Row menu sizing (stx-row-menu)
- Purpose: Make per‑row status dropdown compact and right‑aligned.
- Usage: Add `stx-row-menu` to the dropdown menu container.

### 6) Activity list
- Purpose: Latest events with relative timestamps and absolute tooltip.
- Markup: `<div id="stx-activity"> ...rendered list… </div>`
- JS: `loadActivity()` and `renderActivity()` handle relative/absolute time.

### 7) Controls toolbar (stx-controls)
- Purpose: Bulk action buttons aligned left/right.
- Markup: `<div class="stx-controls d-flex"><div class="stx-controls-left">…</div><div class="stx-controls-right ml-auto">…</div></div>`

## Where the code lives
- CSS: `modules/transfers/stock/assets/css/dashboard.css`
- JS:  `modules/transfers/stock/assets/js/dashboard.js`
- View example: `modules/transfers/stock/views/dashboard.php`

## Reuse checklist
1. Include the CSS/JS files once on your page.
2. Use the exact class names above and preserve the minimal markup skeletons.
3. For typeahead, call `bindTypeahead(input, menu)` after the DOM is ready and ensure the outlet list is loaded via `populateFilters()`.
4. For tables, add an empty‑state row for zero results.
5. Keep the `stx-` prefix for anything you plan to reuse within the Stock Transfers module; use a different module prefix if adding unrelated features.

## Conventions
- Prefix per module: `stx-` (stock transfers). For other modules, choose a short, unique prefix.
- Keep components narrow in scope—compose, don’t override Bootstrap globally.
- Prefer name‑only text in UI; keep IDs available in `title` attributes for hover.

If you want, I can spin up a small styleguide page that renders each component in isolation for quick copy/paste and visual QA.

---

See also:
- REFERENCE_MAP: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/REFERENCE_MAP.md (DOM IDs, classes, JS functions)
- CHANGELOG: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/CHANGELOG.md (what changed and when)
- KNOWLEDGE_BASE: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/KNOWLEDGE_BASE.md (architecture and FAQs)