# Receive API Contract (Stock Transfers)

Base: https://staff.vapeshed.co.nz/modules/transfers/receive/ajax/handler.php

Headers
- Content-Type: application/json
- X-Request-Id: optional; echoed back if provided
- X-API-Key: test bypass (non-prod)

Envelope
{ ok: boolean, ...data, request_id: string }

Actions
1) get_shipment
- Request: { action:"get_shipment", transfer_id:number }
- Response: { ok:true, shipment_id:number|null, parcels:[{ id, box_number, weight_kg }], items:[{ id, product_id, sku, name, expected, received }], request_id }

2) scan_or_select
- Request: { action:"scan_or_select", transfer_id:number, type:"item"|"tracking", value:string, qty?:number }
- Response: { ok:true, receipt_id:number, item_id?:number, delta:number, request_id }

3) save_receipt
- Request: { action:"save_receipt", transfer_id:number, items:[{ transfer_item_id:number, qty_received:number, condition?:string, notes?:string }] }
- Response: { ok:true, receipt_id:number, request_id }

4) list_discrepancies
- Request: { action:"list_discrepancies", transfer_id:number }
- Response: { ok:true, discrepancies:[{ id, type, qty_expected, qty_actual, notes, created_at }], request_id }

Errors and Security
- Same behaviors as Pack API. Rate limits apply.

Schema
- Requires: transfer_receipts, transfer_receipt_items, transfer_discrepancies.

Notes
- When save_receipt is called, if qty_received differs from the expected quantity (from transfer_items.request_qty), a discrepancy record is automatically created in transfer_discrepancies with type short|over. These are initially status=open and can be resolved later by backfill or manual adjustment.

Examples
POST https://staff.vapeshed.co.nz/modules/transfers/receive/ajax/handler.php
{ "action":"get_shipment", "transfer_id": 1234 }

POST https://staff.vapeshed.co.nz/modules/transfers/receive/ajax/handler.php
{ "action":"save_receipt", "transfer_id": 1234, "items": [ { "transfer_item_id": 9876, "qty_received": 8, "notes":"box dented" } ] }
