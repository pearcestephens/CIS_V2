# Migrations

This folder contains idempotent SQL migrations intended to be run against the CIS database.

## Files
- `2025-09-22_transfer_logs.sql` – Creates `transfer_logs` and `transfer_audit_log` if they don't exist; adds reporting indexes.

## Running

Option A: Use your preferred MySQL client and run the SQL file.

Option B: From a server with DB access, run:

```sh
# Replace with actual credentials or use a .my.cnf with defaults
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" "$DB_NAME" < modules/migrations/2025-09-22_transfer_logs.sql
```

Notes:
- The script uses `CREATE TABLE IF NOT EXISTS` and `CREATE INDEX IF NOT EXISTS` (MySQL 8.0.13+). On older versions, you may need to check existing indexes first or remove the `IF NOT EXISTS` lines for indexes.
- All JSON columns are `json` type; if using MariaDB < 10.4, you may prefer `longtext` with `CHECK (json_valid(col))`.

Option C: PHP runner (admin or internal token required)

Visit:

- https://staff.vapeshed.co.nz/modules/migrations/run.php

Or from curl with internal token (non-prod only):

```sh
curl -H "X-Internal-Token: $INTERNAL_API_TOKEN" https://staff.vapeshed.co.nz/modules/migrations/run.php
```

The runner:
- Ensures `schema_migrations` exists
- Executes all `.sql` files in this folder in lexical order
- Records filename, checksum, status into `schema_migrations`
