# Component Index – Purchase Orders

Catalog of reusable UI components and hooks for the Purchase Orders module. Mirrors the Stock Transfers doc style for consistency.

## Scan + Progress Header (Receive)
- Container: `.po-receive` within `receive.php`
- Elements:
  - `#barcode_input` (input)
  - `#manual_search` (button)
  - `.receiving-stats` block with:
    - `#progress_bar` + `#progress_text`
    - `#items_received`, `#total_items`
- JS hooks:
  - listen for Enter/scan on `#barcode_input`
  - button triggers manual search modal/list
  - update progress via `updateProgress(received, total)`

## Receiving Table
- Markup: `#receiving_table`
  - Columns: image, product, expected, in-stock (optional), received, status, actions
  - Footer totals: `#total_expected`, `#total_received_display`
- JS rendering (receive.table.js):
  - `renderReceivingRow(item)` – returns <tr> for an item
  - `refreshTotals()` – compute footer
  - `applyStatusStyles(status)` – badge styles

## Action Bar
- Buttons: `#btn-quick-save`, `#btn-submit-partial`, `#btn-submit-final`
- Behaviors:
  - Quick Save → `po.save_progress`
  - Partial Submit → `po.submit_partial`
  - Final Submit → `po.submit_final`
  - Confirm dialogs recommended for final submit

## Admin Dashboard – Tabs
- Scope: `.po-admin` (admin/dashboard.php)
- Receipts Table: `#tbl-receipts` + pager (`#rcp-prev`, `#rcp-page`, `#rcp-next`)
- Events Table: `#tbl-events` + pager (`#evt-prev`, `#evt-page`, `#evt-next`)
- Inventory Queue Table: `#tbl-queue` + pager (`#q-prev`, `#q-page`, `#q-next`)
- Evidence Form + Table: `#evidence-form`, `#tbl-evidence`
- Common JS (admin.dashboard.js): loaders `loadReceipts()`, `loadEvents()`, `loadQueue()`, `loadEvidence()`

## Styling
- Receive view CSS: `assets/css/receive.css`
- Admin dashboard CSS: `assets/css/admin.dashboard.css`
- Scoping: Use `.po-receive` and `.po-admin` to avoid bleed into other modules.

## Reuse Guidance
- Keep component classes scoped (e.g., `.po-...`) to avoid collisions with Stock Transfers `.stx-...` styles.
- Shared patterns (tables, pagers, small input groups) align with Bootstrap defaults; prefer utility classes over heavy custom CSS.
- Extract repeated UI parts to partials if used across multiple screens (e.g., a small pager component).
