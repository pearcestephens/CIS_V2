# Purchase Orders – Design

This document defines the PO lifecycle, data model, and flows aligned with Ecigdis standards and the existing queue/contract architecture.

## Lifecycle & States

- DRAFT: Created, editable; not sent to supplier.
- APPROVED (optional gate): Business approval before send (feature flaggable).
- SENT: Issued to supplier (email/EDI/API). Immutable item pricing unless amendment created.
- ACKNOWLEDGED: Supplier confirms receipt and optionally ETA (via webhook/API or operator input).
- PARTIAL_RECEIVED: One or more receipts posted; remaining qty on backorder.
- RECEIVED: Fully received (sum of receipts meets ordered - cancelled/backordered). Closeable.
- CLOSED: Administrative closure (e.g., zero remaining or written off) after reconciliation.
- CANCELLED: Voided; no further receipts allowed.

Allowed transitions enforce monotonic progression with controlled amendments (e.g., price/qty changes create a revision and audit entries).

## Core Tables (outline)

- purchase_orders (id, supplier_id, status, currency, incoterm, expected_date, notes, created_at, updated_at, approved_by, sent_at, ack_at, closed_at, cancelled_at, ext_ref, version, idempotency_key)
- po_items (id, po_id, sku, product_id, description, qty_ordered, qty_cancelled, unit_cost, tax_rate, discount, uom, expected_date, backorder_policy)
- po_receipts (id, po_id, receipt_no, received_at, received_by, reference, ext_ref, notes)
- po_receipt_items (id, receipt_id, po_item_id, qty_received, unit_cost_override, location_id, lot_code, expiry)
- po_invoices (id, po_id, supplier_invoice_no, invoice_date, subtotal, tax, total, currency, ext_ref, xero_bill_id)
- po_invoice_lines (id, invoice_id, po_item_id, qty_invoiced, unit_cost, tax_rate, gl_account)
- po_landed_costs (id, po_id, type, amount, currency, allocation_method, supplier_id, notes)
- po_events (id, po_id, type, payload_json, created_at, created_by, correlation_id)
- po_audit (id, po_id, action, actor, details_json, created_at)
- po_webhook_endpoints (id, supplier_id, url, secret, enabled, last_success_at)
- po_idempotency (id, scope, key, request_hash, response_json, created_at, expires_at)

All writes must be parameterized via project DB wrappers. Add indexes on (supplier_id, status), (po_id), and unique (scope,key) for idempotency.

## Flows

- Create & Approve: Create DRAFT → optional APPROVED → SENT (emit queue jobs for supplier notification).
- Receive: Post receipts (supports multiple partial receipts; item-level overrides for cost if needed). Update status to PARTIAL_RECEIVED/RECEIVED automatically.
- 3-Way Match: Attach supplier invoice; match quantities and costs against PO and receipts; create Xero bill via queue when within tolerances; flag discrepancies.
- Backorders: Remaining qty automatically tracked; optional auto-cancel policy per line or supplier SLA.
- Amendments: After SENT, create revisions with delta events; keep immutable history.

## Security & Compliance

- Internal UI (staff): session auth + CSRF.
- Partner API: HMAC-SHA256 signature with timestamp and nonce, per-supplier secret; IP allowlist optional.
- Idempotency: X-Idempotency-Key required for side-effecting requests; store and replay consistent response.
- Audit: All state transitions and external interactions recorded in po_events/po_audit.

## Observability

- Structured logs with correlation_id/request_id across UI, API, and queue jobs.
- Metrics: counts and latency for create/send/receive/invoice, error rates, and match outcomes.
- Health checks: https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/health (200 OK)
- UI Router examples:
  - Receive: https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive
  - Admin:   https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin

## Integration

- Queue Contracts (examples):
  - po.notify_supplier_email
  - po.notify_supplier_edi
  - po.push_to_vend
  - po.create_xero_bill
  - po.reconcile_xero_bill

Each contract payload contains correlation_id, po_id, attempt, and a signed snapshot. Retries with backoff; dead-letter handling via queue.

