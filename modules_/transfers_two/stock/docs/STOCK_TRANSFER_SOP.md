# Stock Transfer Team — Operator SOP

This is a practical guide for operators. It maps UI buttons, traffic lights, and toggles to what they do and when to use them. It aligns our CIS Stock module with the Lightspeed X-Series "Consignments (Transfers)" workflow and our internal Queue service.

All URLs are absolute to https://staff.vapeshed.co.nz.

---

## 1) What the transfer pipeline does

Goal: Move stock between outlets using Lightspeed Consignments.

Stages:
- Create consignment (OPEN with lines)
- Update consignment status (SENT, RECEIVED)
- Edit lines (add/remove/replace)
- Cancel (if needed)

How it runs:
- UI or upstream action enqueues a job
- Worker picks up job and talks to Lightspeed API
- Results recorded in audit/logs; dashboard shows status and KPIs

---

## 2) Your core dashboard

Open: https://staff.vapeshed.co.nz/assets/services/queue/modules/lightspeed/Ui/dashboard.php

Tabs:
- Overview: Health checks, config snapshot
- Webhooks: Recent events + stats (read-only visibility)
- Queue: Queue status, Dead Letter Queue (DLQ) controls
- Tools: Migrations, utilities, simulate trace, event stats, feature toggles

Trace Viewer (optional): If enabled by toggles, a "Trace Viewer" button appears to inspect traces correlating intake → queue → API.

---

## 3) Traffic lights and buttons

Traffic light concept:
- Green: All key health checks return 200 and queue moving
- Yellow: Degraded (latency or retries elevated)
- Red: Failures or kill-switch engaged

Where to check:
- Service Health in Overview tab shows status and latency for:
  - health.php
  - metrics.php
  - webhook.health.php
- Queue tab → Queue Status shows pending/working/fail counts
- Tools tab → Stats (events/transfers) for recent volumes

Operator buttons:
- Tools tab:
  - Simulate Trace: generate a test trace across logs (good for smoke checking)
  - Refresh buttons next to widgets to reload data
  - Feature Toggles: enable/disable parts of the system (token required)
- Queue tab:
  - Purge DLQ: delete old DLQ entries (be careful)
  - Redrive DLQ: requeue dead letters (be careful)

---

## 4) Feature toggles (what and when)

Toggle panel: Tools tab, token-gated (ADMIN_BEARER_TOKEN required)

Key toggles:
- Global Kill Switch (queue.kill_all)
  - Use for emergency freeze: stops workers, scheduling, requeues, DLQ actions, and most mutations.
  - Webhook intake will also be blocked if you set webhook.enabled=false.
- Webhook Enabled (webhook.enabled)
  - Turn off if you need to stop inbound event intake from Lightspeed (e.g., storms).
- Runner Enabled (queue.runner.enabled)
  - Stop workers from processing jobs. Safe pause without tearing down cron.
- Outbound HTTP Enabled (vend.http.enabled)
  - Hard stop on API writes/reads to Lightspeed. Use if vendor is having issues or during controlled maintenance.
- Webhook Fanout Enabled (webhook.fanout.enabled)
  - Control whether incoming webhook events spawn downstream jobs (product, inventory, customer, sale sync jobs).
- UI: Show Trace Viewer (queue.ui.trace_viewer_enabled)
  - Purely UI; shows/hides Trace Viewer link.

Tips:
- To safely halt all mutations: set queue.kill_all=true and vend.http.enabled=false, and optionally webhook.enabled=false to avoid backlogs.
- To test end-to-end without vendor writes: keep vend.http.enabled=true but keep inventory write commands disabled (vend.inventory.enable_command=false). The current build already no-ops writes by default.

---

## 5) Contracts and job “types”

The worker supports these job types (internal routing):
- create_consignment — Create an OPEN transfer with lines
- update_consignment — Set status to SENT or RECEIVED with quantities
- edit_consignment_lines — Modify transfer lines (add/remove/replace)
- add_consignment_products — Add lines to an existing consignment
- cancel_consignment — Mark as CANCELLED when supported
- mark_transfer_partial — Mark internal state as partial (no vendor call)
- webhook.event — Process a queued webhook record and (optionally) fan out
- pull_products / pull_inventory / pull_consignments — Placeholder pulls (acknowledge-only today)
- inventory.command — Inventory set/adjust (guarded; no-op unless explicitly enabled by flags)

Notes:
- Every job records audit entries (best-effort) and logs metrics like job duration.
- Mapping to transfers table is updated by handlers to reflect vendor identifiers and status transitions (when columns/tables exist).

---

## 6) How to run workers (cron)

Cron should call the CLI runner: `run-jobs.php`

Flags that could block:
- queue.runner.enabled=false → runner exits immediately with JSON: { ok:false, error:'runner_disabled' }
- queue.kill_all=true → loop halts after a log message runner.killed

Runtime budgets and concurrency are controlled by configuration:
- vend_queue_runtime_business (seconds per invocation)
- vend.queue.max_concurrency.default and vend.queue.max_concurrency.<type>

---

## 7) DLQ management

DLQ pages:
- Redrive: https://staff.vapeshed.co.nz/assets/services/queue/public/dlq.redrive.php
- Purge:   https://staff.vapeshed.co.nz/assets/services/queue/public/dlq.purge.php

When to redrive vs purge:
- Redrive: When a transient error (rate limit/outage) likely resolved.
- Purge: When entries are stale/irrecoverable (e.g., schema mismatch and permanently invalid payload).

Guardrails:
- Both endpoints are blocked if runner disabled or global kill (you’ll see JSON error like dlq_redrive_disabled).

---

## 8) Webhooks, requeue, and replay

Webhook intake endpoint:
- https://staff.vapeshed.co.nz/assets/services/queue/public/webhook.php
- If disabled: returns 503 with error webhook_disabled

Requeue events:
- https://staff.vapeshed.co.nz/assets/services/queue/public/webhook.requeue.php
- Blocked when webhook disabled.

Health and stats:
- https://staff.vapeshed.co.nz/assets/services/queue/public/webhook.health.php
- https://staff.vapeshed.co.nz/assets/services/queue/public/webhook.stats.php
- https://staff.vapeshed.co.nz/assets/services/queue/public/webhook.events.php
- https://staff.vapeshed.co.nz/assets/services/queue/public/webhook.events.detail.php

---

## 9) Stock transfer flows (operator checklists)

### A) Create a new transfer (OPEN)

Pre-checks:
- Outbound HTTP Enabled = true
- Runner Enabled = true

Trigger job (via UI button or upstream action) that enqueues create_consignment with:
- source_outlet_id, dest_outlet_id
- lines: [{ product_id, qty, sku? }]
- optional idempotency_key

Observe:
- Queue Status shows job picked up
- Audit logs show “consignment.create”
- Transfers mapping updated with vend ids if available
- If Trace Viewer enabled, search by recent traceId

### B) Mark transfer SENT

Pre-checks:
- Outbound HTTP Enabled = true

Enqueue update_consignment with:
- consignment_id
- status: SENT
- lines: [{ product_id, qty }]

Observe:
- “consignment.update” audit; transfers status becomes “sent”

### C) Mark transfer RECEIVED

Pre-checks:
- Outbound HTTP Enabled = true

Enqueue update_consignment with:
- consignment_id
- status: RECEIVED
- lines: [{ product_id, qty }]

Observe:
- transfers status becomes “received”

### D) Edit lines

Enqueue edit_consignment_lines or add_consignment_products
Check audit for “consignment.edit_lines” or “consignment.add_products”

### E) Cancel

Enqueue cancel_consignment with consignment_id
Observe transfers status becomes “cancelled”

### F) Mark Partial (internal only)

Enqueue mark_transfer_partial with transfer_pk or transfer_public_id
No vendor API call, just internal status + audit

Edge safety:
- If you must halt mid-flight: set queue.kill_all=true; jobs will stop after the current batch.
- If vendor unstable: set vend.http.enabled=false to prevent any API calls immediately.

---

## 10) Troubleshooting

Something’s “stuck”:
- Check Overview → Health endpoints all 200?
- Check Queue tab → see pending/working counts.
- Check Tools → Stats (events/transfers) to see throughput.
- If jobs are not moving: Is queue.runner.enabled=true? Is queue.kill_all=false?

Lightspeed API failures:
- Set vend.http.enabled=false temporarily; DLQ will accumulate instead of repeated retry calls.
- After resolution, redrive DLQ.

Webhooks stopped:
- Is webhook.enabled=true?
- Webhook health endpoint returning 200?

See exact flags:
- Open https://staff.vapeshed.co.nz/assets/services/queue/public/flags.php

Try a smoke test:
- Tools → Simulate Trace (creates a synthetic trace across logs)

---

## 11) Safety checklist before doing risky actions

Confirm kill switches are set appropriately:
- For live writes: queue.kill_all=false, queue.runner.enabled=true, vend.http.enabled=true
- For a dry freeze: queue.kill_all=true OR queue.runner.enabled=false, vend.http.enabled=false, webhook.enabled=false

DLQ:
- Redrive only when root cause fixed
- Avoid toggling multiple flags repeatedly; prefer a steady state during a test window

---

## 12) Who to call

If the dashboard shows consistent red and you cannot self-recover with flags:
- Escalate to IT Manager/Security Lead per company SOP
- Share the JSON from flags.php and the last 10 lines of queue logs
