# Knowledge Base – Stock Transfers Dashboard

Authoritative notes for maintainers and future contributors.

## Architecture
- View layer: `views/dashboard.php` renders containers only; all dynamic content via JS.
- Styles: `assets/css/dashboard.css` – scoped with `.stx-dash` prefix, plus component classes.
- Scripts: `assets/js/dashboard.js` – single module providing formatting, fetch, render, and wiring.
- AJAX router: `ajax/handler.php` – CSRF-protected (unless internal token); maps logical actions to `actions/*.php`.
- Dev state: `core/DevState.php` + `testing/.state.json` used for non-prod mocking.

## Data enrichment
- When `cis_pdo()` is available, server enriches `from_name`/`to_name` from `vend_outlets (id, name)`.
- UI renders names; IDs are preserved in `title` tooltips and accepted in filters.

## UX conventions
- Names only in UI; IDs available on hover.
- Relative times in tables/activity; exact times on hover.
- Sticky headers; 500px scroll height; compact dropdowns and buttons.
- Clear chips (×) in labels use `[data-clear]` handler to reset inputs.

## Accessibility
- Buttons and chips are keyboard-focusable.
- Tooltips convey hidden identifiers.
- Consider adding ARIA roles for typeahead listbox and options if extended.

## Performance
- Server caps list sizes (500/5000) before pagination.
- Client renders only visible rows; minimal DOM churn.
- Typeahead limits suggestions to 50 and sorts by name for the initial view.

## Security
- No secrets in code; uses CSRF tokens; internal token path for non-prod testing only.
- Destructive actions guarded with confirmations; delete restricted to cancelled state.

## Reuse
- See `COMPONENT_INDEX.md` and `REFERENCE_MAP.md` for classes/IDs and JS APIs.
- Keep `stx-` prefix for Stock Transfers. Use a dedicated prefix if building other module UIs.

## FAQs
- Why don’t I see outlet names? Likely `cis_pdo()` unavailable or outlets table missing. UI falls back to IDs.
- Why can’t I delete a received transfer? Only cancelled transfers can be deleted by design.
- Where are my dev transfers? See `testing/.state.json`. Use `DevState::deleteOne()` to purge entries.

## Next steps candidates
- Saved views with deep-linking
- Auto-refresh toggle (Live mode)
- SSE/WebSocket for activity
- Server-side fuzzy match for outlets

## Printer/Pack – Final Form (2025-09-24)

Summary
- Header now shows transfer type, From → To outlet names, and display ID.
- Lock banner communicates editor control clearly with Idle/Saving/Read-only states and a Request Edit path.
- Live “Saving…” indicator blinks on input/change and returns to Idle after a short debounce.
- Final form sections: Summary of Transfer (SKUs, Units, Estimated Weight, Estimated Boxes, Total Cost placeholder), Delivery Method (availability chips, internal 80mm Box Slips by estimated box count), Notes, Shipping & Labels (3-step guidance), and Finalise (Mark Ready).

Availability Policy
- Carrier availability (NZ Post / NZ Couriers via GSS) is determined exclusively by tokens stored on the current `vend_outlets` record. Environment/wrapper fallbacks are removed from the availability check; the UI chips reflect this.

Endpoints in use
- `get_transfer_header`, `list_items`, `get_product_weights`, `get_printers_config`, `mark_ready`.
- Box slips: `/modules/transfers/stock/print/box_slip.php?transfer={id}&box={n}&from={name}&to={name}&car={nzpost|gss}`

Notes
- The “Total Cost Value” field is currently a placeholder pending wiring of per-unit cost sources; see TODO in the code. Prefer supply/avg cost from canonical product tables or PO lines when available.
