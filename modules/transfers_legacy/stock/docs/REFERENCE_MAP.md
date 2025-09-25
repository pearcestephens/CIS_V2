# Reference Map – Stock Transfers Dashboard

A quick lookup for DOM IDs, CSS classes, and JS entry points used across the dashboard.

## DOM IDs (views/dashboard.php)
- stx-stats – KPI container
- stx-open-body – tbody for Open Transfers
- stx-activity – Latest Activity container
- stx-activity-refresh – Refresh button for Activity
- stx-activity-more – Load more Activity
- stx-filter-q – Search input
- stx-filter-state – Status select
- stx-filter-from – From typeahead input
- stx-ta-from – From typeahead menu
- stx-filter-to – To typeahead input
- stx-ta-to – To typeahead menu
- stx-table-body – tbody for Search table
- stx-select-all – Master checkbox
- stx-bulk-select-all – Select All button
- stx-bulk-select-none – Select None button
- stx-bulk-cancel – Bulk cancel
- stx-bulk-delete – Bulk delete
- stx-pg-status – Pagination status
- stx-pg-prev – Prev page button
- stx-pg-next – Next page button

## CSS classes (assets/css/dashboard.css)
- stx-dash – Dashboard container scope
- stx-kpi, stx-kpi--open|motion|arrive|closed – KPI styles
- stx-kpi-value, stx-kpi-icon – KPI internals
- stx-table – Scrollable table wrapper with sticky headers
- stx-typeahead, stx-typeahead-menu, stx-typeahead-item – Typeahead scaffolding
- stx-chip, stx-chip-x – Label clear chip control
- stx-row-menu – Small right-aligned row dropdown menu
- stx-empty – Empty state styling
- stx-controls, stx-controls-left, stx-controls-right – Toolbar layout

## JS entry points (assets/js/dashboard.js)
- STXDash.loadStats, STXDash.loadList, STXDash.loadOpen – Public helpers
- prettyState, relTime, fmtUpdated – Formatting helpers
- rowHtml – Renders a data row
- populateFilters – Loads statuses and outlets list
- bindTypeahead – Binds typeahead to an input + menu
- wireFilters – Hooks up events for filters, pagination, bulk actions
- loadActivity, renderActivity – Activity feed logic

## AJAX routes (ajax/handler.php)
- get_dashboard_stats → actions/get_dashboard_stats.php
- list_transfers → actions/list_transfers.php
- list_outlets → actions/list_outlets.php
- get_activity → actions/get_activity.php
- set_status, cancel_transfer, delete_transfer → state changes

## Data contracts (summarized)
- list_transfers: { rows:[{ transfer_id, state, from, to, from_name?, to_name?, created_at, updated_at }], pagination }
- list_outlets: { outlets:[{ id, name }] }
- get_activity: { items:[{ transfer_id, state, latest_at, from, to, from_name?, to_name?, flag_count }] }

Tip: Grep for `stx-` to find everything at once.

## Related Template Routes
- Pack: https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack&transfer={id}
- Outgoing: https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=outgoing

Always use absolute URLs under https://staff.vapeshed.co.nz when linking from docs.