# Purchase Orders (PO) Module

This module implements Purchase Orders as a first-class workflow separate from Stock Transfers.

Authoritative links:
- Receive (UI): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive
- Index (UI): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index
- Admin dashboard (UI): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin
- AJAX base (internal staff): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php (POST only)
	- Health (GET): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php?ajax_action=health
- API base (partner/supplier): https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/ (planned)

Key goals:
- Robust PO lifecycle (Draft → Sent → Partial/Received → Closed/Cancelled)
- Idempotent, auditable operations with queue-backed side effects (Vend/Lightspeed, Xero)
- Partial receipts, backorders, and 3-way match (PO, GRN, invoice)
- Pluggable supplier connectors (Email+CSV, SFTP, EDI/AS2, Vendor APIs)

See detailed specs:
- Design: https://staff.vapeshed.co.nz/modules/purchase-orders/DESIGN.md
- API Spec: https://staff.vapeshed.co.nz/modules/purchase-orders/API_SPEC.md

## Verification & Operations

Request tracing
- All endpoints emit `X-Request-ID` and include `request_id` in JSON envelopes.

Envelope contract
- `{ "success": true|false, "data"?: {...}, "error"?: {"code","message"}, "request_id": "..." }`

CSRF (mutations)
- Mutating actions require a valid CSRF token via `X-CSRF-Token` header (403 on fail).

Health checks
- Lightweight GET returns `{ success:true, data:{ module:"purchase-orders", status:"healthy", time }, request_id }`.
- Intended for monitoring; does not require login.

Idempotency (receive + stock)
- Use `Idempotency-Key` per attempt. Server stores `request_hash` + `response_json` in `idempotency_keys`.
- Replay with same key + same body returns identical response. Same key + different body → 409 `idem_conflict`.
- Covered endpoints: `save_progress`, `submit_partial`, `submit_final`, `update_live_stock`.

Schema shims (optional)
- `schema/004_optional_vend_shims.sql` adds helpful indexes and sets utf8mb4_unicode_ci collation for vend mirror tables.
	- vend_products(sku), vend_inventory(outlet_id), vend_outlets(name), vend_suppliers(name)

Quick lint
```mysecureshell
php -l $(git ls-files '*.php')
```

Migrations runner
- https://staff.vapeshed.co.nz/modules/purchase-orders/schema/migrate.php
