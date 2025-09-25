# Pack API Contract (Stock Transfers)

Base: https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php

Headers
- Content-Type: application/json
- X-Request-Id: optional; returned if not provided
- X-API-Key: test bypass (non-prod)
- Idempotency-Key: optional for create actions (label); enabled in handler for generate_label
 - CSRF: required for browser-origin POSTs (standard CIS token)

Envelope
{ ok: boolean, ...data, request_id: string }

Actions
0) health (GET or POST)
- Request: GET https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?action=health
- Response: { ok:true, service:"transfers.stock", status:"healthy", time:string, request_id }

1) calculate_ship_units
- Request: { action: "calculate_ship_units", product_id: number, qty: number }
- Response: { ok: true, ship_units: number, unit_g: number, weight_g: number, request_id }

2) validate_parcel_plan
- Request: { action: "validate_parcel_plan", transfer_id: number, parcel_plan: { parcels: [{ weight_g:number, items?: [{ item_id?:number, product_id?:string, qty:number }] }] } }
- Response: { ok: true, attachable: [...], unknown: [...], notes: {...}, request_id }

3) generate_label
- Request: { action:"generate_label", transfer_id:number, carrier?:"MVP"|"NZ_POST"|..., parcel_plan?:{...} }
- Response (MVP): { ok:true, shipment_id:number, parcels:[{ id, box_number, weight_kg, items_count }], skipped:[], request_id }
- Response (error): { ok:false, error:string, request_id }
Notes:
- When COURIERS_ENABLED != 1, handler will create shipments in `internal_drive` mode using MVP persistence only.
- Idempotency enabled: same Idempotency-Key will replay the previous response.

4) save_pack
- Request: { action:"save_pack", transfer_id:number, notes?:string }
- Response: { ok:true, request_id }

5) list_items
- Request: { action:"list_items", transfer_id:number }
- Response: { ok:true, items:[{ id, product_id, sku, name, requested_qty, unit_g, suggested_ship_units }], request_id }

6) get_parcels
- Request: { action:"get_parcels", transfer_id:number }
- Response: { ok:true, shipment_id:number|null, parcels:[{ id, box_number, weight_kg, items_count }], request_id }

Errors
- 400 invalid input
- 404 unknown action
- 415 unsupported content type
- 429 rate limited
- 500 unhandled exception (see logs)

Security
- CSRF enforced for browsers; X-API-Key bypass for CLI in non-prod
- HSTS, XFO, nosniff, referrer-policy headers applied by middleware
- Idempotency optional; requires idempotency_keys table
