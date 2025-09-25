# Stock Transfers (Unified) — Workflow & API

This module implements the “Stock” transfer workflow aligned with Lightspeed X-Series Consignment lifecycle.
It replaces legacy `stock-transfers` paths but remains backward-compatible.

Primary pages:
- Dashboard (dev endpoint): https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php
- Outgoing (template):  https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=outgoing
- Pack (template):      https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack&transfer={id}

Template route (preferred for linking):
- Stock dashboard: https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=stock

Routing & Template Notes:
- Use real .php endpoints during development (no extensionless routes).
- Prefer the template alias: https://staff.vapeshed.co.nz/modules/module.php
- Nested module paths are supported (e.g., `module=transfers/stock`), enabling per-view meta resolution from `modules/transfers/stock/views/stock.meta.php`.
- The CIS template now resolves views from `/modules/{module}/views/{view}.php` and safely sanitizes paths.

## Lifecycle States

Defined in `modules/transfers/stock/core/States.php`:

- draft → packing → ready_to_send → sent/in_transit → receiving → partially_received → received
- cancel allowed from draft/packing/ready_to_send, and policy-gated after sent (pre-receipt only)

Transitions are validated via `TransferState::canTransition($from, $to)`.

## Service Layer

`modules/transfers/stock/core/TransferService.php` orchestrates operations. Integration points for the Queue/Vend client are clearly marked.

Methods:
- createDraft(from_outlet:int, to_outlet:int, user:int)
- addItems(transfer_id:int, items: [{product_id:int, qty:int}], user:int)
- markReady(transfer_id:int, user:int)
- send(transfer_id:int, user:int, shipment:array)
- receive(transfer_id:int, user:int, items:array, final:bool)
- cancel(transfer_id:int, user:int)
- status(transfer_id:int)

Config:
- `modules/transfers/stock/core/Config.php`: CANCEL_GRACE_MINUTES = 30

## AJAX Router & Endpoints

Router: https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php (POST unless noted)

New endpoints (Queue-ready contracts):
- create_draft: from_outlet, to_outlet, csrf
- add_items: transfer_id, items[], csrf
- finalize_pack: transfer_id, csrf (produces transfer_packed job)
- send_transfer: transfer_id, shipment{}, csrf
- receive_partial: transfer_id, items[], csrf (produces transfer_received_partial job)
- receive_final: transfer_id, items[], csrf (produces transfer_received_final job)
- cancel_transfer: transfer_id, csrf
- get_status (GET supported): transfer_id

Responses: `{ success: bool, data|error, request_id }`

Back-compat endpoints retained: add_products, pack_goods, send_transfer, receive_goods, create_label_*, save_manual_tracking, mark_ready, merge_transfer

## Monday 07:00 Auto-Creation

Requirement: Automatically create draft transfers on Mondays at 07:00 for required outlet pairs.

Plan:
1) Cron/Task triggers a small PHP script calling `TransferService::createDraft()` for each pair.
2) Staff continue packing → finalize_pack → send_transfer.
3) Cancel grace: 30 minutes from packing (enforced server-side and reflected in UI).

Note: The scheduler script will live under `modules/transfers/stock/tools/cron_create_monday.php` (to be added) and be invoked by a mysecureshell-compatible cron entry.

## Receiving Flow

- After `send_transfer`, the transfer is receiving-only.
- Receiving staff can:
	- Add products that arrived but weren’t in the original pack (items[] with qty)
	- Set missing items to qty=0
	- Save partial deliveries (`receive_partial`), and complete later
	- Finalize when done (`receive_final`), which ends the consignment lifecycle

## Security

- All AJAX is CSRF and session-auth protected.
- Non-production testing path: When APP_ENV is not production, you can bypass session+CSRF by sending header `X-Internal-Token` that matches env var `INTERNAL_API_TOKEN`. Optionally set `X-Actor-ID` to impersonate a user id (defaults to 1 if omitted).
- Absolute URLs to https://staff.vapeshed.co.nz only.
- No secrets in repo; app.php provides bootstrap and secure headers.

## Integration with Queue / Lightspeed X-Series

These actions are intentionally thin and will delegate to the queue client:
- createConsignment → `send_transfer`
- createConsignmentProduct → during packing and receives
- receive operations → from `receive_partial`/`receive_final`

Reference:
- Lightspeed X-Series API (consult internal notes; validate external links separately per policy)

Operational & Integration Docs:
- SOP (Operators): https://staff.vapeshed.co.nz/modules/transfers/stock/docs/STOCK_TRANSFER_SOP.md
- Queue Integration Spec (Engineering): https://staff.vapeshed.co.nz/modules/transfers/stock/docs/QUEUE_INTEGRATION_SPEC.md

## Notes for Devs

- Use `modules/module.php?module=transfers&view=stock` in links (dev).
- Prefer `modules/module.php?module=transfers/stock&view=stock` for proper nested resolution.
- UI JS should call the new endpoints and display state badges (draft/packing/ready/sent/receiving/partial/received/canceled).
- `pack.php` guards missing transfer and redirects to dashboard.

### Non-Production Testing (Internal Token)

Set an environment variable on the web app:
- `INTERNAL_API_TOKEN=your-long-random-token`
- Optional: `INTERNAL_ACTOR_ID=101`

Use curl with the internal token (no session or CSRF needed):

Finalize pack
```sh
curl -sS -X POST 'https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php' \
	-H 'Accept: application/json' \
	-H 'X-Internal-Token: REPLACE_WITH_INTERNAL_API_TOKEN' \
	-H 'X-Actor-ID: 101' \
	--data 'action=finalize_pack' \
	--data 'transfer_id=12345'
```

Send transfer
```sh
curl -sS -X POST 'https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php' \
	-H 'Accept: application/json' \
	-H 'X-Internal-Token: REPLACE_WITH_INTERNAL_API_TOKEN' \
	-H 'X-Actor-ID: 101' \
	--data 'action=send_transfer' \
	--data 'transfer_id=12345' \
	--data-urlencode 'shipment[carrier]=NZPost' \
	--data-urlencode 'shipment[tracking]=TRACK123'
```

Receive partial / final
```sh
curl -sS -X POST 'https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php' \
	-H 'Accept: application/json' \
	-H 'X-Internal-Token: REPLACE_WITH_INTERNAL_API_TOKEN' \
	-H 'X-Actor-ID: 101' \
	--data 'action=receive_partial' \
	--data 'transfer_id=12345' \
	--data-urlencode 'items[0][product_id]=67890' \
	--data-urlencode 'items[0][qty]=2'

curl -sS -X POST 'https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php' \
	-H 'Accept: application/json' \
	-H 'X-Internal-Token: REPLACE_WITH_INTERNAL_API_TOKEN' \
	-H 'X-Actor-ID: 101' \
	--data 'action=receive_final' \
	--data 'transfer_id=12345' \
	--data-urlencode 'items[0][product_id]=67890' \
	--data-urlencode 'items[0][qty]=2'
```

Security notes:
- The internal token bypass is automatically disabled in production (APP_ENV in [prod, production, live]).
- Keep `INTERNAL_API_TOKEN` rotated and stored in env/secret manager, not in code.

## Pack View — Quickstart, Template URL, and Printer Tokens

Canonical links (use the template route in app navigation):

- Template route (preferred):
	- https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack&transfer={ID}
- Direct dev endpoint (for local debugging only):
	- https://staff.vapeshed.co.nz/modules/transfers/stock/pack.php?transfer={ID}

Behavior and data source:

- The Pack view renders within the CIS template using a plain layout and compact dashboard-style UI.
- Line items hydrate from the canonical `transfer_items` table and enrich with `vend_products.name` and `vend_inventory.inventory_level` (for the source outlet). It safely falls back to legacy `stock_transfer_lines` where the canonical table is not available.
- CSRF is exposed via a meta tag and hidden input; all AJAX posts include CSRF unless using non-prod internal token bypass.

Pack-Only mode (server guard + optional banner):

- Server-side guard (blocks send/finalize endpoints when enabled):
	- Set environment variable: `TRANSFERS_STOCK_PACKONLY=1`
- Optional banner for UI clarity (does not enforce server policy):
	- Append `&packonly=1` to the Pack view URL

Printers and labels (status and tokens):

- Status endpoint (POST): https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=get_printers_config
	- Returns `{ success, data: { has_nzpost: bool, has_gss: bool, default: "nzpost"|"gss"|"manual" }, request_id }`
	- Requires CSRF unless using internal token in non-production.
- Tokens / enablement (policy):
	- Availability signalling (chips/tabs) is determined exclusively from tokens stored on the active outlet’s `vend_outlets` record. Environment/wrapper fallbacks are not used for signalling.
	- Downstream label creation should also prefer outlet-scoped credentials. If environment wrapper helpers exist during transition, treat them as legacy and plan migration to outlet tokens.
- Label creation endpoints (POST):
	- NZ Post:  https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=create_label_nzpost
	- GSS:      https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=create_label_gss
	- Manual:   https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=save_manual_tracking

Quick verification (happy path):

1) Navigate to the template Pack URL with a valid `{ID}`. The page should show a single compact header, from → to outlets, and a sticky items table.
2) Confirm items render from `transfer_items` (product names present; planned = requested − already sent).
3) Inspect the network call to `get_printers_config` — it should return the carrier availability based solely on tokens present on the current `vend_outlets` record.
4) Toggle between carriers; unavailable carrier tabs are hidden automatically.
5) Create a manual tracking entry to verify the fallback path; posting should succeed and update shipment history.

See also: Pack Quickstart — https://staff.vapeshed.co.nz/modules/transfers/stock/docs/PACK_QUICKSTART.md

## Changelog / Recent Fixes

- 2025-09-21: Fixed CIS template to support nested module paths; updated dispatcher to `module=transfers/stock`; added `views/stock.php` alias resolving to dashboard; eliminated “Module view not found.” on Stock dashboard.
