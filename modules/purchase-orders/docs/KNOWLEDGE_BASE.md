# Knowledge Base – Purchase Orders
## Health & Monitoring
- Internal AJAX health (unauthenticated GET):
  - https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php?ajax_action=health
  - Response: `{ success:true, data:{ module:"purchase-orders", status:"healthy", time }, request_id }`
- Partner API health (planned):
  - https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/health
  - Response: `{ success:true, data:{ status:"ok" }, request_id }`

## Acceptance Checklist (Ops)
- [ ] Health GET returns 200 with request_id
- [ ] CSRF enforced on mutating actions (403 on fail)
- [ ] Idempotency replay works for save_progress/submit_partial/submit_final/update_live_stock
- [ ] Evidence upload/list/assign works and updates the PO link
- [ ] Product search returns ≤20 results with indexed lookups
- [ ] Admin lists paginate and return totals


Architecture, conventions, and operational notes for the Purchase Orders module.

## Overview
- Purpose: Receive Purchase Orders efficiently, with partial and final submit flows, and provide an Admin dashboard for receipts, events, queue monitoring, and evidence management.
- Tech: PHP 8.x, Bootstrap views, vanilla JS modules (`receive.*.js`, `admin.dashboard.js`), AJAX POST endpoints with CSRF and auth.

## Entry Points
- User landing (via CIS Template router): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index
- Receive (works without specifying a PO initially): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive&po_id={id}
- Admin: https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin
- AJAX (POST only): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php

## Security & Session
- All POST actions require: logged-in user, CSRF token verified (po_verify_csrf()).
- Admin dashboard injects CSRF via `.po-admin[data-csrf]` for JS to include in requests.
- Server logs correlated by request ID `$__PO_REQ_ID` (set in tools.php) and exposed as `X-Request-ID` header.
- Idempotency supported on receive + stock endpoints via `Idempotency-Key` header; server stores `request_hash` and envelope for deterministic replays.

## Data Flow
- Receive screen boot:
  1) Load PO via `po.get_po` → render rows
  2) Scan or search → `po.search_products` or direct match
  3) Update quantities → local state → `po.save_progress`
  4) Submit partial/final → `po.submit_partial` or `po.submit_final`
  5) Optional live stock sync per item → `po.update_live_stock`
  6) Evidence assign → `po.assign_evidence`
- Admin dashboard:
  - Tab loaders call admin.* endpoints with pagination and optional filters (PO ID, status, outlet).

## Error Handling
- All endpoints return envelopes `{ success, data|error, request_id? }`.
- UI shows toasts/alerts; final submit paths require confirmation and surface receipt IDs on success.

## Performance
- Table rendering uses lightweight DOM updates (batch insert or innerHTML string assembly).
- Paginated admin lists; avoid large payloads.
 - Vend product search is index-backed (sku) and capped to 20 results.

## Conventions
- IDs and classes scoped to `.po-receive` and `.po-admin`.
- AJAX actions prefixed with `po.` for user flows and `admin.` for admin lists.
- Absolute asset URLs under https://staff.vapeshed.co.nz/ to match global includes.
 - Envelopes must include `request_id`; CSRF failures return 403.

## Maintenance
- Add new endpoints by mapping in `ajax/handler.php`.
- Keep docs updated when IDs/JS change. Mirror the 4-file structure used in Stock Transfers for consistency.

## FAQ
- Q: Why POST-only?
  A: Enforces CSRF validation and keeps parameters out of URL logs for sensitive actions.
- Q: How to test locally?
  A: Use the module router with a known `po_id`. Verify CSRF token is present in `.po-admin` for admin actions.
 - Q: How to use idempotency?
   A: Send `Idempotency-Key` (UUID). The same key+body replays; different body returns 409 conflict. See `idempotency_keys` table.
 - Q: Where do vend product searches read from?
   A: From `vend_products` mirror if present; otherwise, action returns empty results.
