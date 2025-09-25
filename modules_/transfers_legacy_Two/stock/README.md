# Transfers – Stock Pack Module

Base endpoint: https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php

## Endpoints
- Health (GET): https://staff.vapeshed.co.nz/modules/transfers/stock/ajax/handler.php?action=health
- JSON actions (POST): see docs/PACK_API_CONTRACT.md

## Security
- CSRF enforced for browser POST requests
- Optional X-API-Key bypass for CLI/testing in non-production
- HSTS + security headers via middleware
- Idempotency for create actions using `Idempotency-Key` header

## Behavior Notes
- When COURIERS_ENABLED != 1, `generate_label` operates in internal_drive MVP mode and persists shipments/parcels/items without contacting couriers.

## Acceptance Checklist
- [ ] Health endpoint returns 200 and ok:true with request_id
- [ ] calculate_ship_units validates input and returns computed units
- [ ] validate_parcel_plan returns attachable/unknown/notes
- [ ] generate_label enforces idempotency replay and persists MVP data
- [ ] save_pack requires CSRF and stores notes
- [ ] list_items/get_parcels respond quickly (<700ms dev) with request_id
- [ ] Security headers present, CSRF enforced, rate limiting active

## Logs & Observability
- X-Request-ID propagated; JSON envelope includes `request_id`
- Lightweight perf sample via cis_profile_flush if available

See also: docs/PACK_API_CONTRACT.md
