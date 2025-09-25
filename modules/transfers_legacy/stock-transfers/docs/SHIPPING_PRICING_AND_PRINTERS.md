# Shipping, Pricing, and Printer Integration

This document captures database schema, pricing sources, API endpoints, and printer behavior for the Stock Transfers module.
Note: The active module lives under `modules/transfers/stock/`. This `stock-transfers` path remains as a legacy doc holder while code is consolidated.

Last updated: 2025-09-24

---

## 1) Database Schema (Normalized)

Tables created under `modules/transfers/stock-transfers/schema/`:

- `carriers`
  - `carrier_id` (PK), `code` (UNIQUE), `name`, `active`, timestamps
- `carrier_services`
  - `service_id` (PK), `carrier_id` (FK), `code`, `name`, unique per carrier
- `containers`
  - `container_id` (PK), `carrier_id` (FK), `service_id` (FK nullable), `code`, `name`, dimensions (mm), `max_weight_grams`, `max_units`
- `pricing_rules`
  - `rule_id` (PK), `carrier_id` (FK), `service_id` (FK nullable), `container_id` (FK nullable), `price`, `currency`, `effective_from`, `effective_to`, timestamps
- `surcharges`
  - `surcharge_id` (PK), `carrier_id` (FK), `code`, `name`, `price`

SQL files:
- `001_shipping_pricing.sql` – creates tables
- `002_seed_shipping_pricing.sql` – inserts seed rows for NZ Post + NZ Couriers (GSS)
- `003_views.sql` – creates reporting/compat views: `pricing_matrix`, `freight_rules_compat`

Legacy compatibility:
- View `freight_rules_compat` mirrors your original `freight_rules` concept: exposes `container`, `max_weight_grams`, `max_units`(NULL), `cost`, timestamps.

---

## 2) Seed Pricing (as provided)

NZ Post (incl GST):
- Letters (≤500g unless noted):
  - Medium Letter (130×235×6mm) – $2.90
  - Large Letter (165×235×10mm) – $4.20
  - Oversize Letter (≤1kg, 260×385×20mm) – $5.50
- Boxes:
  - Size 1 (235×165×70mm) – $2.50
  - Size 2 (250×185×170mm) – $4.50
  - Size 3 (350×265×200mm) – $5.00
  - Size 4 (318×216×507mm) – $6.50
  - Size 5 (418×286×540mm) – $8.50
  - Wine (460×140×120mm) – $5.00

NZ Couriers (GSS) ticket pricing (incl GST):
- Local Delivery (≤0.1m³ or 25kg): $11.50
- Outer Area (≤15kg or 0.025m³): $12.00
- Shorthaul (≤15kg or 0.025m³): $15.00
- Longhaul (≤5kg or 0.025m³): $21.00
- Inter-Island (≤5kg or 0.025m³): $38.00
Surcharges:
- Rural: $8.00
- Saturday: $8.00
- Residential Zone: $3.50
- R18 Restricted: $6.80

Note: These are retail guidance numbers supplied here for presets/documentation; live label creation will follow the carrier APIs and your contract terms.

---

## 3) Carrier APIs and Catalog

Use carrier APIs to discover products/services and to review previous shipments for real usage patterns per outlet. You indicated the “Hamilton East” token provides the widest range.

Endpoints (AJAX router): `https://staff.vapeshed.co.nz/modules/transfers/stock-transfers/ajax/handler.php`
- Planned:
  - `get_shipping_catalog` – Fetches live catalog (services/containers) from carrier APIs and caches to DB.
  - `get_popular_services` – Aggregates past shipments by outlet to suggest top services/containers.
- Implemented now:
  - `create_label_nzpost` – Creates NZ Post shipment label (accepts JSON packages and service code).
  - `create_label_gss` – Creates NZ Couriers booking/label (accepts JSON packages and service code).
  - `save_manual_tracking` – Saves manual tracking numbers (URL or ID supported; URL gets parsed server-side).
  - `sync_shipment` – Reconciles server shipment record (boxes + tracking IDs) post label creation.

Wrapper expectations (server-side):
- `nzpostCreateShipment_wrapped($transferId, array $packages, string $service, string $ref, array $ctx)`
- `gssCreateShipment_wrapped($transferId, array $packages, string $service, array $ctx)`
- `saveManualTracking_wrapped($transferId, string $trackingId, string $notes, int $userId, int $simulate, string $carrierCode, string $labelUrl, string $requestId)`
- `syncShipment_wrapped($transferId, string $carrierCode|null, string $trackingId|null, int $userId, int $simulate, string $requestId)`

Security:
- CSRF required via header or form field.
- Server logs include `request_id` for correlation.
 - Availability signalling in the UI (chips/tabs) must reflect tokens stored on the current outlet’s `vend_outlets` row only; do not rely on environment wrappers for signalling.

---

## 4) Printer UI/Behavior

Key scripts:
- `assets/js/printer.js` – action posting, status, and label creation event (`stx:label:created`).
- `assets/js/shipping.np.js` & `assets/js/shipping.gss.js` – carrier-specific UI logic (lazy-loaded).
- `assets/js/shipping.manual.js` – minimalist manual entry editor. Accepts URL or ID, parses, saves each row.
- `assets/js/outgoing.init.js` – binds actions, initializes printer, auto-syncs shipments after any label creation.

UI elements:
- Printer card shows default printer, auto-open/auto-print toggles, and a status badge.
- “Sync Shipment” button available near printer status to reconcile shipment data on demand.
- Manual tracking section:
  - Add rows of “URL/ID”, parse to clean ID, and save all rows.

Events:
- `stx:label:created` (detail = `{ label_url?, tracking_number? }`)
  - Auto-open label, copy tracking number to clipboard, status update
  - Triggers `sync_shipment` in background for data accuracy.

---

## 5) Data Flow Summary

1) User prepares parcels and selects carrier/service.
2) Client posts to `create_label_*` with JSON `packages` and service.
3) Server wrapper calls carrier API and returns `{ tracking_number, label_url, ... }`.
4) Client:
   - Opens label URL, optionally prints
   - Saves tracking ID automatically (legacy or via `record_shipment` if integrated)
   - Calls `sync_shipment` to refresh server-side shipment/boxes/tracking state.
5) Dashboard/history can query `pricing_matrix` and carrier events (future polling) for analytics.

---

## 6) Extending Pricing / Updates

- Add containers or services: insert into `containers` / `carrier_services` with `carrier_id` and dimension constraints.
- Adjust prices: upsert into `pricing_rules` with `effective_from`/`effective_to` for versioning.
- Add surcharges: upsert into `surcharges` with `carrier_id`.
- Use view `pricing_matrix` for a flat list of current prices by service/container.

Seed data can also be sourced from JSON at:
- `assets/data/shipping_pricing.json` (created for front-end hints and offline defaults)

---

## 7) Operational Notes

- Tokens: Use the Hamilton East token when querying catalog/history to get the best coverage of services/containers.
- Backward compatibility: URL parsing for manual tracking ensures pasting a link “just works”.
- Reconciliation: Always let the page auto-sync after label creation, or click “Sync Shipment” to update immediately.

---

## 8) Future Work (recommended)

- Implement `get_shipping_catalog` and `get_popular_services` actions to hydrate DB from carriers.
- Add a polling worker to fetch shipment events and populate tracking analytics.
- Expose a pricing admin UI to edit `pricing_rules` with date ranges and preview via `pricing_matrix`.
- Enforce dimensional/weight validation using `containers.max_weight_grams` and service constraints at label creation time.
