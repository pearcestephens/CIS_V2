# Unified Transfers Model

This module consolidates multiple transfer types under a single model and UI pattern:

- Stock Transfers (Outlet → Outlet)
- Juice Transfers (Facility → Facility)
- In-Store Transfers (Within Outlet)

It mirrors a Lightspeed Consignment-style schema: header + lines, origin/destination, status lifecycle, optional shipping/tracking. Purchase Orders are separate because they involve a Supplier party rather than a destination outlet.

## Core Header Fields

- id (int)
- transfer_type (enum: STOCK | JUICE | INSTORE)
- source_outlet_id / outlet_from
- dest_outlet_id / outlet_to
- status (enum: OPEN | READY | SENT | RECEIVED) or numeric 0..3 mapped to those states
- created_at, updated_at
- Optional: tracking_number, carrier/courier, notes

## Lines

- product_id
- qty_planned / qty_to_transfer
- qty_picked / qty_transferred_at_source
- qty_counted_at_destination
- Optional: notes, min_qty_to_remain

## Lifecycle

1) OPEN (Draft) → 2) READY (Packed) → 3) SENT → 4) RECEIVED

Numeric schemas (0,1,2,3) are normalized to the enum names.

## Purchase Orders (Exception)

Purchase Orders share the header+lines pattern but differ in the “destination party”: they have a Supplier (vendor) instead of an outlet destination.

Therefore POs live outside this module’s path and retain a dedicated UI/workflow at:

- https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index

## Dashboards

- All Transfers Dashboard: https://staff.vapeshed.co.nz/modules/transfers/dashboard.php
- Stock Dashboard: https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php
 - Pack (CIS Template): https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack&transfer={id}
 - Outgoing (CIS Template): https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=outgoing

## Base Helpers

- `modules/transfers/base/model.php` includes Types, Status, and Model normalization helpers.
