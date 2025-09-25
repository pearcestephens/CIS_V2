# Knowledge Base – Summary

This summary captures key cross-module behaviors and recent updates.

## Purchase Orders (2025-09-25)
- Request tracing via `X-Request-ID` and envelope `request_id` across endpoints.
- CSRF hardened: failures return 403 with standard envelope.
- Idempotency with request hashing for: save_progress, submit_partial, submit_final, update_live_stock.
- Evidence flows secured and paginated; `assign_evidence` validates and updates `po_evidence.purchase_order_id`.
- Product search added (vend mirror backed) with ≤ 20 results and SKU index.
- Schema shims now include helpful BTREE indexes and utf8mb4_unicode_ci collation.

Acceptance Checklist
- Shims applied; indexes present; collation uniform.
- Envelope consistency; CSRF enforced.
- Idempotency replays and 409 conflicts as expected; rows present in `idempotency_keys`.
- Search fast and typed, capped; evidence assignment persists; stock update queues and optional live write.

Links
- PO README: https://staff.vapeshed.co.nz/modules/purchase-orders/README.md
- PO Docs: https://staff.vapeshed.co.nz/modules/purchase-orders/docs/
# CIS Modules — Knowledge Base Snapshot (2025-09-24)

Authoritative, high-level map of modules under https://staff.vapeshed.co.nz/modules/ with conventions, routes, AJAX endpoints, and where to find deeper docs. Keep this file updated when adding modules or changing entry points.

## Global Conventions

- Router (dev-safe query): https://staff.vapeshed.co.nz/modules/module.php?module={module}&view={view}
- Router (pretty, requires .htaccess): https://staff.vapeshed.co.nz/module/{module}/{view}
- Content-only views render inside CIS chrome provided by template; include: https://staff.vapeshed.co.nz/modules/_shared/template.php
- Per-view meta file alongside view: returns title, breadcrumb, layout, assets. See: https://staff.vapeshed.co.nz/modules/README.md
- Asset helpers inside views/blocks:
  - CSS: `tpl_style('https://staff.vapeshed.co.nz/modules/{module}/assets/css/file.css')`
  - JS: `tpl_script('https://staff.vapeshed.co.nz/modules/{module}/assets/js/file.js', ['defer' => true])`
- Security: CSRF on POST endpoints, absolute URLs under https://staff.vapeshed.co.nz, never expose secrets. See: https://staff.vapeshed.co.nz/modules/README.md

---

## Module Summaries

### Transfers (Base)
- Docs: 
  - Overview: https://staff.vapeshed.co.nz/modules/transfers/README.md
  - Design: https://staff.vapeshed.co.nz/modules/transfers/DESIGN.md
- Purpose: Unified model for Stock, Juice, and In-Store transfers. Enum lifecycle OPEN → READY → SENT → RECEIVED.
- Key routes:
  - All Transfers Dashboard: https://staff.vapeshed.co.nz/modules/transfers/dashboard.php
  - Stock Transfers Dashboard: https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php
   - Pack (Template): https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack&transfer={id}
   - Outgoing (Template): https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=outgoing
- Helpers live under: https://staff.vapeshed.co.nz/modules/transfers/base/

-#### Stock Transfers (Submodule)
- Docs index: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/
- Deep docs:
  - Reference Map: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/REFERENCE_MAP.md
  - Component Index: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/COMPONENT_INDEX.md
  - Knowledge Base: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/KNOWLEDGE_BASE.md
  - Changelog: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/CHANGELOG.md
  - Queue Integration Spec: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/QUEUE_INTEGRATION_SPEC.md
  - Pack Quickstart: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/PACK_QUICKSTART.md
- AJAX base: https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php
- Typical actions: `get_dashboard_stats`, `list_transfers`, `list_outlets`, `get_activity`, plus state changes. See Reference Map for IDs/classes and JS entry points.

### Purchase Orders
- Docs hub: https://staff.vapeshed.co.nz/modules/purchase-orders/README.md
- Deep docs:
  - Design: https://staff.vapeshed.co.nz/modules/purchase-orders/DESIGN.md
  - API Spec: https://staff.vapeshed.co.nz/modules/purchase-orders/API_SPEC.md
  - Reference Map: https://staff.vapeshed.co.nz/modules/purchase-orders/docs/REFERENCE_MAP.md
  - Component Index: https://staff.vapeshed.co.nz/modules/purchase-orders/docs/COMPONENT_INDEX.md
  - Knowledge Base: https://staff.vapeshed.co.nz/modules/purchase-orders/docs/KNOWLEDGE_BASE.md
  - Changelog: https://staff.vapeshed.co.nz/modules/purchase-orders/docs/CHANGELOG.md
- User routes:
  - Index: https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index
  - Receive UI: https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive (accepts optional `po_id`)
  - Admin: https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin
- AJAX base (POST only): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
- Common actions: `po.get_po`, `po.save_progress`, `po.submit_partial`, `po.submit_final`, `po.search_products`, `po.update_live_stock`, plus `admin.*` for lists.

### Audit Viewer (Admin)
- Docs: https://staff.vapeshed.co.nz/modules/audit/README.md
- Purpose: Browse/investigate `transfer_audit_log` and allied logs with filters, pagination, and detail modal.
- Route (moved): https://staff.vapeshed.co.nz/modules/module.php?module=_shared/admin/audit&view=viewer

### Migrations
- Docs: https://staff.vapeshed.co.nz/modules/migrations/README.md
- SQL set (examples): https://staff.vapeshed.co.nz/modules/migrations/2025-09-22_transfer_logs.sql
- Runner: https://staff.vapeshed.co.nz/modules/migrations/run.php (requires admin or internal token as described in README)

### MODULE_TEMPLATE (Scaffold)
- Docs: https://staff.vapeshed.co.nz/modules/MODULE_TEMPLATE/README.md
- Purpose: Canonical starting point with POST-only AJAX router, tools helpers, assets, idempotent migrator, and view stubs.
- Quick start links:
  - Pretty route (after copy): https://staff.vapeshed.co.nz/module/__MODULE_SLUG__/index
  - Query route (dev-safe): https://staff.vapeshed.co.nz/modules/CIS_TEMPLATE.php?module=__MODULE_SLUG__&view=index

### Juice Transfer
- Files present (no dedicated docs found): dashboard/list/create pages, API, controller, tests, assets.
- Likely entry pages (legacy endpoints):
  - Dashboard: https://staff.vapeshed.co.nz/modules/juice-transfer/juice_transfer_dashboard.php
  - Create: https://staff.vapeshed.co.nz/modules/juice-transfer/juice_transfer_create.php
  - List: https://staff.vapeshed.co.nz/modules/juice-transfer/juice_transfer_list.php
- API: https://staff.vapeshed.co.nz/modules/juice-transfer/api/juice_transfer_api.php
- Note: Consider aligning this module with the Transfers base conventions and adding the standard docs set.

---

## Central Docs Index
- Modules Index: https://staff.vapeshed.co.nz/modules/docs/INDEX.md

## Maintenance Checklist
- When adding/updating a module:
  1) Add the 4-file docs set (REFERENCE_MAP.md, COMPONENT_INDEX.md, KNOWLEDGE_BASE.md, CHANGELOG.md) under the module’s `docs/` folder.
  2) Ensure all routes are accessible via the CIS template router.
  3) Keep CSS/JS under 25KB each; use absolute asset URLs under https://staff.vapeshed.co.nz.
  4) Update https://staff.vapeshed.co.nz/modules/docs/INDEX.md and this summary file.
  5) For endpoints, enforce POST + CSRF; return `{ success, data|error, request_id }` envelopes.

Policy Notes (2025-09-24)
- Transfers printer/pack UI simplified into a final form; see Stock Transfers Knowledge Base and Pack Quickstart.
- Carrier availability chips use tokens sourced strictly from `vend_outlets` for the active outlet; environment/wrapper fallbacks are not used for signalling.

## Notes
- This snapshot is generated from docs currently in the repo as of 2025-09-24.
- If any link 404s in non-prod, verify web server mapping for raw `.md` files; the paths are authoritative within the repository.
