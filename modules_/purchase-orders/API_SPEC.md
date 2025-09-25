# Purchase Orders – API Spec (v1)

Base URL (partner/supplier): https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/
Base URL (internal UI AJAX): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php

Envelope:
- Request and response JSON use `{ "success": true|false, "data"|"error": {…}, "request_id": "uuid", "meta": {…} }`.

Headers (partner API):
- Authorization: HMAC key (e.g., `X-API-Key: <public_key>`)
- X-Signature: hex-encoded HMAC-SHA256 over `timestamp + method + path + body`
- X-Timestamp: UNIX epoch seconds
- X-Idempotency-Key: UUID for side-effecting operations
- Content-Type: application/json

## Endpoints

1) Create PO
- POST https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders
- Body: `{ supplier_id, currency, expected_date, notes, items: [{ sku, product_id?, description?, qty, unit_cost, tax_rate?, uom? }] }`
- Response: `{ success, data: { po_id, status: "DRAFT" }, request_id }`
- Idempotent via X-Idempotency-Key.

2) Get PO
- GET https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}
- Response: `{ success, data: { po, items, receipts, invoices }, request_id }`

3) Update PO (pre-send)
- PATCH https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}
- Body: partial update for header or items (only in DRAFT/APPROVED)
- Response: `{ success, data: { po }, request_id }`

4) Send PO
- POST https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}/send
- Body: `{ channel: "email"|"edi"|"api", recipients?: ["a@b"], notes? }`
- Response: `{ success, data: { status: "SENT" }, request_id }`
- Emits queue job: `po.notify_supplier_*`.

5) Acknowledge PO (supplier)
- POST https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}/ack
- Body: `{ reference?, expected_date?, notes? }`
- Response: `{ success, data: { status: "ACKNOWLEDGED" }, request_id }`

6) Post Receipt (GRN)
- POST https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}/receipts
- Body: `{ receipt_no?, received_at?, reference?, items: [{ po_item_id|sku, qty_received, unit_cost_override?, location_id?, lot_code?, expiry? }] }`
- Response: `{ success, data: { receipt_id, status: "PARTIAL_RECEIVED"|"RECEIVED" }, request_id }`
- Idempotent by (po_id + client receipt_no) via X-Idempotency-Key.

7) Attach Supplier Invoice
- POST https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}/invoices
- Body: `{ supplier_invoice_no, invoice_date, lines: [{ po_item_id|sku, qty_invoiced, unit_cost, tax_rate? }], totals?: { subtotal, tax, total } }`
- Response: `{ success, data: { invoice_id }, request_id }`
- Emits queue job: `po.create_xero_bill`.

8) Get 3-Way Match Result
- GET https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}/match
- Response: `{ success, data: { status: "MATCHED"|"VARIANCE", variances: [{ item, qty_delta, cost_delta }] }, request_id }`

9) Cancel PO
- POST https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/purchase-orders/{po_id}/cancel
- Body: `{ reason }`
- Response: `{ success, data: { status: "CANCELLED" }, request_id }`

10) Health
- GET https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/health
- Response: `{ success: true, data: { status: "ok" }, request_id }`

10b) Health (internal AJAX)
- GET https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php?ajax_action=health
- Response: `{ success:true, data:{ module:"purchase-orders", status:"healthy", time }, request_id }`

## Errors

- 400: validation_error (details per field)
- 401: auth_required
- 403: forbidden
- 404: not_found
- 409: conflict (state, idempotency)
- 422: unprocessable (business rule violation)
- 429: rate_limited
- 500: internal_error

Error envelope: `{ success:false, error:{ code, message, details? }, request_id }`.

## Notes

- All state transitions are validated server-side; PATCH is limited to allowed states.
- Receiving auto-updates PO status; remaining/backorder quantities computed per line.
- Idempotency datastore keeps request hash and canonical response for 24-72h.
- All endpoints emit po_events and write po_audit.
