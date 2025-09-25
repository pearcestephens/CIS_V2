# CHANGELOG – Purchase Orders
## 2025-09-25
- Added request tracing header `X-Request-ID` for all PO endpoints and ensured envelopes include `request_id`.
- Enforced CSRF failures return HTTP 403 with a structured envelope.
- Implemented idempotency with request hashing across receive flows and stock updates:
  - save_progress.php, submit_partial.php, submit_final.php, update_live_stock.php
  - Replays return identical envelopes; conflicts produce 409 `idem_conflict`.
- Hardened evidence flows:
  - list_evidence pagination and filters; assign_evidence now validates and updates `po_evidence.purchase_order_id`.
- Implemented product search against vend mirror (when available), capped to 20, fields: product_id, name, sku, image.
- Schema shim (004) now adds useful BTREE indexes and utf8mb4_unicode_ci collation.

- Health endpoint (internal AJAX): GET https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php?ajax_action=health
  - Returns `{ success:true, data:{ module:"purchase-orders", status:"healthy", time }, request_id }`


All notable changes to the Purchase Orders module docs.

## 2025-09-24
- Corrected authoritative links to use the CIS Template router for index/receive/admin views.
- Clarified that the receive view can open without an initial PO ID and will prompt for selection.
- Emphasized absolute URLs under https://staff.vapeshed.co.nz and POST-only AJAX base.

## 2025-09-22
- System-wide telemetry routing added: page view + page performance (via CIS template) now sink into audit log under entity_type 'purchase_order.page' (minimal Logger)
- Added initial doc set:
  - REFERENCE_MAP.md
  - COMPONENT_INDEX.md
  - KNOWLEDGE_BASE.md
  - CHANGELOG.md
- Captured DOM IDs, admin tabs, AJAX routes, and data contracts at a high level.
