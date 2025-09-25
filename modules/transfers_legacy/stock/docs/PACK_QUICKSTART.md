# Pack View Quickstart — Stock Transfers

This guide summarizes how to access, use, and verify the Pack page within the CIS template, plus how printer carrier availability is determined.

## URLs

- Preferred (CIS Template):
  - https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack&transfer={ID}
- Direct dev endpoint (debug only, may lack CIS chrome):
  - https://staff.vapeshed.co.nz/modules/transfers/stock/pack.php?transfer={ID}
  - Note: For production use, always prefer the CIS Template route above.

Notes:
- The CIS template renders the Pack view with a compact, dashboard-style UI. The meta file intentionally leaves the top title/subtitle blank to avoid duplicate headers.

## Data Source

- Items hydrate from the canonical `transfer_items` table, enriched with `vend_products.name` and `vend_inventory.inventory_level` at the source outlet.
- Planned quantity: `max(qty_requested - qty_sent_total, 0)`.
- If the canonical table is unavailable, the view falls back to legacy `stock_transfer_lines` where possible.

## Security

- CSRF token is exposed via a meta tag and a hidden input; all Pack AJAX requests include this token.
- Non-production internal token bypass is supported by the AJAX router for testing:
  - Send header `X-Internal-Token: $INTERNAL_API_TOKEN` and optional `X-Actor-ID`.
  - Disabled automatically in production.

## Pack-Only Mode

- Enforce server guard (block send/finalize): set `TRANSFERS_STOCK_PACKONLY=1` in the environment.
- Optional visual banner: add `&packonly=1` to the Pack URL.

## Printers — Status & Tokens (policy)

- Status endpoint: https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=get_printers_config (POST)
  - Returns: `{ success, data: { has_nzpost, has_gss, default }, request_id }`
  - Requires CSRF unless using internal token bypass in non-prod.
- Enable carriers (enforced source = vend_outlets only):
  - NZ Post / NZ Couriers tokens are sourced exclusively from `vend_outlets` rows for the current outlet context.
  - Environment/wrapper fallbacks have been removed from availability signalling to prevent misconfiguration.
  - If an outlet lacks a token, chips will show “Unavailable”; printing UIs should degrade gracefully.
- Label creation:
  - NZ Post:  https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=create_label_nzpost
  - GSS:      https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=create_label_gss
  - Manual:   https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?ajax_action=save_manual_tracking

## Quick Checks

1) Navigate to the template Pack URL with a valid transfer ID.
2) Confirm one compact header and sticky items table are visible.
3) Verify items display with product names and planned quantities.
4) Confirm network request to `get_printers_config` returns availability based on vend_outlets tokens (no env fallbacks).
5) Test manual tracking save to verify non-label flow works.

## Notes

- All assets are absolute to https://staff.vapeshed.co.nz and include cache-busting via filemtime.
- Breadcrumbs are provided by the view meta and rendered via the CIS template.
