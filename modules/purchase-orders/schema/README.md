# Purchase Orders Schema

Location: https://staff.vapeshed.co.nz/modules/purchase-orders/schema/

This folder contains idempotent SQL files to provision the core Purchase Orders schema, inventory adjustment queue, events/receipts ledger, and optional shims for Vend product/inventory mirrors.

Order of application:
1. 001_po_core.sql
2. 002_inventory_adjust_requests.sql
3. 003_po_events_receipts.sql
4. 004_optional_vend_shims.sql

Apply via the migration runner:

```mysecureshell
php modules/purchase-orders/schema/migrate.php
```

Notes:
- Files use CREATE TABLE IF NOT EXISTS and a few ADD COLUMN IF NOT EXISTS where supported (MariaDB/MySQL 8.0+). Review on older versions.
- Existing installs with equivalent tables will be left intact.
- Adjust table/column types only via explicit migrations; these files avoid destructive changes.

Safety:
- Always back up the database before running schema changes.
- Run on staging first; validate the app boots and receiving flows work.
