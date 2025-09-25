# Queue Integration Spec — Stock Transfers

Author: Operations + Engineering
Scope: Align CIS Stock module with Lightspeed X-Series Consignments via our Queue service.
URLs are absolute to https://staff.vapeshed.co.nz.

Note: As of 2025-09-24, the printer/pack UI uses a simplified “final form” and signals carrier availability strictly from vend_outlets tokens (no env fallbacks). See PACK_QUICKSTART.md for UI details.

---

## 1) Intake and Orchestration

- Requests (UI/automation) enqueue standardized jobs in `ls_jobs` with an idempotency key.
- Runner picks jobs respecting feature flags, concurrency caps, and kill switch.
- Vendor calls via Lightspeed client (idempotent where supported).
- We record audit (immutable), logs (operational), and mapping (transfers table).

Feature Toggles (controls):
- queue.kill_all, queue.runner.enabled, vend.http.enabled, webhook.enabled, webhook.fanout.enabled
- inventory.kill_all, inventory.pipeline.enabled, vend.inventory.enable_command (writes default disabled)

---

## 2) Job Types (Contracts)

- create_consignment: OPEN transfer with lines [{product_id, qty, sku?}]
- update_consignment: status SENT | RECEIVED with lines
- edit_consignment_lines: add/remove/replace line quantities
- add_consignment_products: append lines to an existing consignment
- cancel_consignment: cancel vendor transfer (when supported)
- mark_transfer_partial: internal state only (no vendor call)

Id Field Formats:
- Consignment ID: Lightspeed id (string)
- Product identity: product_id (int) is primary; sku optional hint
- Internal references: transfer_pk (int) and transfer_public_id (string)

---

## 3) DB Calling Rules (Non-negotiable)

- PDO only, prepared statements, no string-concat SQL.
- Transactions for multi-row/dependent operations.
- Minimal row locks (SELECT … FOR UPDATE) on hot paths (e.g., line aggregation) to avoid lost updates.
- Idempotency table `cishub_idempotency(intent, idempotency_key, metadata, created_at)` with unique(intent,idempotency_key).
- Audit append-only: `transfer_audit_log`; operator logs: `transfer_logs`.

---

## 4) Semantics: Create / Update / Edit / Cancel

Create (OPEN):
- Body: outlet_from, outlet_to, products[{product_id, qty[, sku]}]
- Idempotency: pass-through; normalize vendor 409 → success if already processed.
- On success, update `transfers` mapping with vend_consignment_id, vend_number, vend_url.

Update (SENT/RECEIVED):
- SENT: send quantity; RECEIVED: send quantity_received.
- Never both in the same call unless vendor allows.
- Map SENT→sent; RECEIVED→received in `transfers.status`.

Edit lines:
- add/remove/replace semantics via PATCH body; remove as qty=0 if supported.

Cancel:
- If API supports, set status=CANCELLED; otherwise internal-only cancel (no vendor lie).

Partial Mark (internal):
- `mark_transfer_partial` flags internal state with outstanding tally; no vendor call.

Guardrails:
- Non-inventory products (has_inventory=false) → no-op + audit.
- Validate outlet ids; reject early otherwise.

---

## 5) Ad Hoc Inventory (Guarded)

- Ops: set/adjust per product/outlet; default disabled (`vend.inventory.enable_command=false`).
- Preconditions: has_inventory=true; outlet permission validated.
- For set: delta = target - current; skip when 0.
- Idempotency key per op: `inventory:product:outlet:op:hash(payload)`.
- Record as events in `cishub_inventory_events` with trace_id and outcome.

---

## 6) Tables & Tiers

Mirrors (read-heavy): `vend_products|ls_products` (has_inventory, sku), `ls_outlets|vend_outlets`, `webhook_events`, `webhook_stats`, `ls_rate_limits`.

Transfers domain (write + audit):
- `transfers`, `transfer_items`, `transfer_logs`, `transfer_audit_log`, `transfer_queue_metrics`.

Tiers:
- Always include source_outlet_id and dest_outlet_id and validate cross-store permissions (future).
- Pricing tiers are separate; never intermix with inventory flows.

---

## 7) Concurrency, Idempotency, Conflicts

- Use row locks for delta computations; singleflight advisory locks for workers per type.
- Write idempotency record first; on duplicate → short-circuit and normalize to success when safe.
- Vendor 409 “already processed” with consistent body → normalize to success to avoid churn.

---

## 8) Performance & Safety

Rate Limits & Retries:
- 429/5xx → bounded retry with backoff + jitter; honor Retry-After.

Observability:
- Per-minute request counters; latency buckets; job duration; audit with timing.

Kill Switches:
- vend.http.enabled=false and/or queue.kill_all=true halt mutations immediately.

---

## 9) Contracts — Examples

create_consignment (request):
```
{
  "source_outlet_id": "101",
  "dest_outlet_id": "205",
  "lines": [
    { "product_id": 123456, "qty": 8, "sku": "COIL-0.2Ω" },
    { "product_id": 987654, "qty": 3 }
  ],
  "idempotency_key": "consignment:create:101:205:2b8b4f90",
  "transfer_pk": 44521,
  "transfer_public_id": "TR-2025-000087"
}
```

create_consignment (normalized response):
```
{
  "success": true,
  "vend": {
    "consignment_id": "778899",
    "number": "C-778899",
    "status": "OPEN"
  },
  "transfer": { "pk": 44521, "public_id": "TR-2025-000087" }
}
```

add_consignment_products:
```
{
  "consignment_id": 778899,
  "lines": [
    { "product_id": 456789, "qty": 4 },
    { "product_id": 123456, "qty": 2, "sku": "COIL-0.2Ω" }
  ],
  "idempotency_key": "consignment:add:778899:9ee3c3ad",
  "transfer_pk": 44521
}
```

edit_consignment_lines (replace):
```
{
  "consignment_id": 778899,
  "replace": [
    { "product_id": 123456, "qty": 10 },
    { "product_id": 987654, "qty": 1 }
  ],
  "idempotency_key": "consignment:replace:778899:1c5b70fe",
  "transfer_public_id": "TR-2025-000087"
}
```

update_consignment (SENT):
```
{
  "consignment_id": 778899,
  "status": "SENT",
  "lines": [
    { "product_id": 123456, "qty": 8 },
    { "product_id": 987654, "qty": 3 }
  ],
  "idempotency_key": "consignment:sent:778899:20250921T0900"
}
```

update_consignment (RECEIVED):
```
{
  "consignment_id": 778899,
  "status": "RECEIVED",
  "lines": [ { "product_id": 123456, "qty": 5 } ],
  "idempotency_key": "consignment:received:778899:20250921T1600"
}
```

cancel_consignment:
```
{
  "consignment_id": 778899,
  "idempotency_key": "consignment:cancel:778899:5f42d1aa",
  "reason": "operator_cancel"
}
```

---

## 10) Scheduling (Monday 07:00 NZT)

- Auto-create OPEN consignments for configured pairs.
- Timezone: Pacific/Auckland.
- Future: optional draft TTL and blackout dates.

---

## 11) Webhooks & Reconciliation

- Store and process as `webhook.event` jobs; optional fan-out.
- Correlate by consignment id; reconcile conflicts; re-poll on ambiguity.

---

## 12) DLQ & Failure Policy

- Transient → DLQ then redrive; Permanent → DLQ with reason and operator fix before redrive.
- Redrive guards: ensure flags allow processing; verify credentials.

---

## 13) Observability

- Include request_id/trace_id/idempotency_key in logs and headers.
- Metrics: job duration, per-minute request counters, latency buckets.
- Health endpoints surfaced in Queue dashboard.

---

## 14) Permissions & Audit

- Roles for pack/send/receive/cancel (RBAC TBD; worker acts as system for now).
- Audit fields: actor, action, before/after JSON, metadata, timing, API excerpts, outlets.
- Cancel grace policy: default 30 minutes (configurable).
