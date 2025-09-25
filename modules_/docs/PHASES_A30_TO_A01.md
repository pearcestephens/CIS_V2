# Transfers Pack & Receive — Full Build Plan (Phases A30 → A01)

Purpose
- Deliver a production‑grade Pack + Receive flow with strong lower‑ware (DB/indexes), middleware (security, ingress, observability), and a usable UI.
- Sequenced from A30 backward to A01 with Steps and Micro‑steps. Migrations are executed at the end, in order.
- Scope is limited to this module set. No unrelated files are touched.

Note
- Feature flags flip advanced features without code edits.
- All API responses must include a request_id.

---

## Phase A30 — Post‑launch polish

Objective: Increase operability without changing core flows.

Step 30.1: Manifest & paperwork
- 30.1.1 Add ManifestBuilder to produce daily manifests per store/date.
- 30.1.2 Endpoint export_manifest returns signed URL for CSV/PDF.
- 30.1.3 Audit event TRANS_EVT_MANIFEST_EXPORTED with count.

Step 30.2: Quality mode
- 30.2.1 QUALITY_MODE=1 enables extra validation (parcel min/max weight, duplicates).
- 30.2.2 Return 422 with violations[] details on failure.

Step 30.3: Kill‑switches
- 30.3.1 /tmp/cis_pause_labels → generate_label returns 503.
- 30.3.2 /tmp/cis_readonly → writes 503; reads OK.

Step 30.4: Rotation & retention
- 30.4.1 Logs: 7d hot, 30d compressed.
- 30.4.2 Labels: purge files after 60d; keep DB metadata.

---

## Phase A29 — Final production flip

Objective: Safe toggles and rollout.

Step 29.1: Flags
- 29.1.1 Staging: COURIERS_ENABLED=1, AUTH_ENFORCED=1, IDEMPOTENCY_ENABLED=1.
- 29.1.2 Prod initial: COURIERS_ENABLED=0, others=1; flip outlets gradually.

Step 29.2: Runbook
- 29.2.1 Freeze deploys; apply migrations.
- 29.2.2 Warm caches (weight cache job).
- 29.2.3 Verify monitor (p95, errors).
- 29.2.4 Flip pilot outlet; expand after 30–60 minutes stable.

Step 29.3: Backout
- 29.3.1 Toggle COURIERS_ENABLED=0 on errors >1%.
- 29.3.2 Keep MVP path as fallback.

---

## Phase A28 — Day‑2 ops kit

Objective: On‑call visibility and tools.

Step 28.1: Request inspector
- 28.1.1 Lookup by request_id; show spans, DB errors, final JSON.
- 28.1.2 Link to transfer/shipment rows if present.

Step 28.2: Safe replay
- 28.2.1 Read‑only actions replay by request_id.
- 28.2.2 Writes only with DRY_RUN=1.

Step 28.3: Slow query tracer
- 28.3.1 Log SQL >200ms with plan and binds.
- 28.3.2 Weekly aggregation report.

---

## Phase A27 — Error budgets & alerts

Objective: Reliability guard rails.

Step 27.1: SLOs
- 27.1.1 calc p95 < 200ms; error < 0.2%.
- 27.1.2 label p95 < 2.5s; error < 1.0%.
- 27.1.3 receive/save p95 < 1.0s.

Step 27.2: Alerts
- 27.2.1 Cron checks monitor; on breach → Slack/email.
- 27.2.2 Include sample error and link to inspector.

---

## Phase A26 — Receipts dashboard & audits

Objective: Operational visibility.

Step 26.1: Dashboard
- 26.1.1 Filters: date, store, status.
- 26.1.2 Columns: id, from→to, parcels, expected/received, discrepancies, updated_at.
- 26.1.3 API: list_transfers w/ pagination.

Step 26.2: Timeline
- 26.2.1 Merge logs + audit into one timeline; drill‑down meta.

---

## Phase A25 — Deployment & source‑of‑truth

Objective: Git‑first, repeatable deploys.

Step 25.1: Optional CI
- 25.1.1 GH Actions: static checks; SSH to pull; run php db/migrate.php.

Step 25.2: Cloudways pull
- 25.2.1 Post‑deploy hook: migrate + warm caches.

---

## Phase A24 — Documentation & contracts

Objective: Clear API and events.

Step 24.1: API contracts
- 24.1.1 For each action: request/response/errors with examples.
- 24.1.2 Version header X‑API‑Version: 1.

Step 24.2: Event dictionary
- 24.2.1 Canonical event names; when to emit; required meta.

---

## Phase A23 — Acceptance tests

Objective: Prove E2E.

Step 23.1: Seed
- 23.1.1 Minimal transfer + items + weights + tokens.

Step 23.2: cURL suite
- 23.2.1 calc → check weights math.
- 23.2.2 validate → unknown[] case.
- 23.2.3 label (empty items) → auto‑attach; persist parcels.
- 23.2.4 get_parcels → counts.
- 23.2.5 receive/save → status transitions + discrepancies.

Step 23.3: Idempotency
- 23.3.1 Same Idempotency‑Key → identical JSON; no duplicates.

---

## Phase A22 — Security tightening

Objective: Close dev gaps.

Step 22.1: CSP
- 22.1.1 Report‑only then enforce; allowlisted origins.

Step 22.2: Cookies
- 22.2.1 SameSite=Strict; Secure; HttpOnly.

Step 22.3: Secrets
- 22.3.1 Encrypt courier tokens at rest (libsodium).

Step 22.4: SSRF & file safety
- 22.4.1 Whitelist carrier endpoints; validate PDF content type.

---

## Phase A21 — Data integrity jobs

Objective: Keep derived data accurate.

Step 21.1: Weight cache
- 21.1.1 Chunked CLI recompute unit_g.

Step 21.2: Parcel counts
- 21.2.1 Nightly recount if materialized.

Step 21.3: DB maintenance
- 21.3.1 ANALYZE weekly; OPTIMIZE on high fragmentation.

---

## Phase A20 — Print monitor & tools

Objective: Queue visibility.

Step 20.1: print_jobs schema and indexes.

Step 20.2: Agent protocol (next/update endpoints; API key auth).

Step 20.3: UI widget showing last 10 jobs with retry.

---

## Phase A19 — Suggest container

Objective: Lightweight rules.

Step 19.1: container_rules table with thresholds.

Step 19.2: Algorithm: first rule by max_weight_g≥grams; else UNKNOWN.

Step 19.3: suggest_container endpoint returns code + reason; session cache.

---

## Phase A18 — Reprint & void

Objective: Corrective actions.

Step 18.1: Reprint using stored label URLs or re‑fetch; enqueue print.

Step 18.2: Void with guards; admin override requires reason; audit.

Step 18.3: Permissions: packer reprints; admin voids.

---

## Phase A17 — Observability & monitor

Objective: Fast insight.

Step 17.1: Exit metrics log {action, ms, code, ids, request_id}.

Step 17.2: Aggregations: p50/p95, counts, top errors for 60m/24h.

---

## Phase A16 — Role‑based auth

Objective: Least privilege.

Step 16.1: user_roles model (packer/receiver/admin).

Step 16.2: Gates on label/void/save_receipt per role.

---

## Phase A15 — Idempotency & throttles ON

Objective: No dupes; flood‑safe.

Step 15.1: Client UUIDv4 per label; send Idempotency‑Key.

Step 15.2: Server idempotency_keys; return stored JSON on repeat.

Step 15.3: Buckets: calc 300/min/IP; label 20/min/IP; receive 120/min/IP.

---

## Phase A14 — Reconciliation & close‑out

Objective: Tie out shipped vs received.

Step 14.1: Deltas per line; discrepancy rows with reason.

Step 14.2: Status: PACKED→IN_TRANSIT→RECEIVING→COMPLETE.

Step 14.3: Force close with audit and justification.

---

## Phase A13 — Receive API & view

Objective: Efficient scanning.

Step 13.1: API: get_shipment, scan_or_select, save_receipt, list_discrepancies.

Step 13.2: View: items vs parcels panes; scan bar.

Step 13.3: UX: hotkeys, toasts, request_id footer.

---

## Phase A12 — Receive module DB

Objective: Solid schemas and FKs.

- transfer_receipts, transfer_receipt_items, transfer_discrepancies with foreign keys and supporting indexes.

---

## Phase A11 — Printing architecture

Objective: Reliable store printing.

- Queue + agent polling; server next/update endpoints; status transitions.

---

## Phase A10 — NZ Post integration

Objective: Second carrier; unified contract.

- Helper class; error translation; persist order metadata.

---

## Phase A09 — GoSweetSpot integration

Objective: Primary carrier integration.

- Helper class; weight conversions; error translation; persistence.

---

## Phase A08 — Courier tokens & orders

Objective: Token storage & orders with encryption at rest.

- outlet_courier_tokens and transfer_carrier_orders; Crypto helper for sealing tokens.

---

## Phase A07 — Pack frontend

Objective: Operator‑friendly UI with idempotency.

- postForm header support; parcel plan builder; in‑flight guards; toasts; request_id footer.

---

## Phase A06 — Pack handler

Objective: Kernel‑only ingress.

- Pipeline: trace → headers → normalizer → csrf/api‑key → CT guard → size guard → rate limits.
- Actions: calc, validate, label, save_pack, list_items, get_parcels, stubs reprint/void.
- JSON envelope with request_id.

---

## Phase A05 — Pack helper refactor

Objective: Transactional service boundary.

- Explicit transaction for label flows; repository‑style helpers; race‑safe with FOR UPDATE.

---

## Phase A04 — Shared libs & events

Objective: Centralized shared logic.

- TransferHelper (resolvers, plan validator); events constants; emit_event wrapper.

---

## Phase A03 — Database fitness

Objective: Indexes, FKs, locking hints.

- Composite indexes for hot paths; FKs for parcels→shipments/items; lock usage in receive.

---

## Phase A02 — Security & middleware v1

Objective: One ingress everywhere.

- Trace, headers, normalizer, CSRF/API‑key, content‑type & size guards, rate limits; optional CORS/maintenance/auth/idempotency.

---

## Phase A01 — Repo & environment baseline

Objective: Predictable environments.

- Directory hygiene; .gitignore; bootstrap & config; secrets only in .env.

---

Definition of Done (every endpoint)
- Middleware pipeline; JSON/FormData; CSRF/auth per env.
- Validated inputs; typed errors with request_id.
- Audit/log events; rate limits; optional idempotency.
- Monitor shows p95 and error rates by action.
- Docs include request/response examples.
