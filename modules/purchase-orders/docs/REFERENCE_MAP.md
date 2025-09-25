# Reference Map – Purchase Orders

A quick lookup for DOM IDs, CSS classes, JS entry points, and AJAX routes used across the Purchase Orders module.

## Views and DOM IDs

### index.php
- Links only; routes to Receive and Admin via CIS Template router:
  - Receive: https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive
  - Admin:   https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin
  - Always use absolute URLs under https://staff.vapeshed.co.nz

### receive.php
- po-receive – container with data-po-id
- barcode_input – scan input
- manual_search – button for manual search
- progress_bar – progress bar element
- progress_text – progress label inside bar
- items_received – numeric text
- total_items – numeric text
- receiving_table – table for items
- th-live-stock – optional live stock column header (hidden by default)
- tf-live-stock – optional live stock footer cell
- total_expected – footer total expected
- total_received_display – footer total received
- action-bar – bottom action row
- btn-quick-save – quick save
- btn-submit-partial – partial submit
- btn-submit-final – final submit

### admin/dashboard.php
- po-admin – container with CSRF data
- po-filter-id – PO ID filter input
- btn-apply-filter – apply filter button
- Tabs:
  - tab-receipts – Receipts table container
    - btn-refresh-receipts – refresh
    - receipts-meta – small meta text
    - tbl-receipts – receipts table tbody target
    - rcp-prev, rcp-page, rcp-next – pagination controls/text
  - tab-events – Events table container
    - btn-refresh-events – refresh
    - events-meta – small meta text
    - tbl-events – events table tbody target
    - evt-prev, evt-page, evt-next – pagination controls/text
  - tab-queue – Inventory Queue container
    - queue-status – status select
    - queue-outlet – outlet filter
    - btn-refresh-queue – refresh
    - queue-meta – small meta text
    - tbl-queue – queue table tbody target
    - q-prev, q-page, q-next – pagination controls/text
  - tab-evidence – Evidence upload/list
    - evidence-form – form wrapper
    - ev-po-id – PO ID input
    - ev-type – evidence type select
    - ev-desc – description
    - ev-file – file input
    - btn-refresh-evidence – refresh list
    - tbl-evidence – evidence table tbody target

## CSS Classes
- po-receive – scope for receiving view
- po-admin – scope for admin dashboard
- receiving-stats – small block for progress

(Additional styles may live under assets/css/*.css, e.g., receive.css, admin.dashboard.css)

## JS Entry Points
- assets/js/receive.core.js – core page bootstrapping and API wiring
- assets/js/receive.table.js – table rendering and updates
- assets/js/receive.actions.js – user actions (save, partial/final submit)
- assets/js/admin.dashboard.js – admin dashboard loaders and tab handlers

Common functions to expect (from code patterns):
- poAjax(action, payload) – AJAX helper (defined in tools.php or JS layer)
- renderReceivingRow(item) – table row template
- updateProgress(received, total) – updates bar/text

## AJAX Routes (ajax/handler.php)
POST only, CSRF and auth required. URL:
https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php

- po.get_po → actions/get_po.php
- po.search_products → actions/search_products.php (accepts GET/POST `q`)
- po.save_progress → actions/save_progress.php
- po.undo_item → actions/undo_item.php
- po.submit_partial → actions/submit_partial.php
- po.submit_final → actions/submit_final.php
- po.update_live_stock → actions/update_live_stock.php
- po.unlock → actions/unlock.php
- po.extend_lock → actions/extend_lock.php
- po.release_lock → actions/release_lock.php
- po.upload_evidence → actions/upload_evidence.php
- po.list_evidence → actions/list_evidence.php
- po.issue_upload_qr → actions/issue_upload_qr.php
- po.assign_evidence → actions/assign_evidence.php

Admin endpoints
 - Health (AJAX): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php?ajax_action=health
- admin.list_events → actions/admin/list_events.php
- admin.list_inventory_requests → actions/admin/list_inventory_requests.php
 - health → ajax/actions/health.php
Tip: grep for `po-` IDs or `po.` AJAX actions to find everything quickly.

Notes: All mutating endpoints require CSRF and support Idempotency-Key. Responses include `request_id` and `X-Request-ID` header for traceability.
