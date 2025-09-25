# Transfers Stock CLI Test Runner

Run smoke tests inside the app (no curl required). This uses the internal token path, sets headers programmatically, and calls the same AJAX handler.

## File
- `https://staff.vapeshed.co.nz/modules/transfers/stock/testing/cli_test_runner.php`

## Usage (SSH)
```bash
# Provide token + actor + tid
php modules/transfers/stock/testing/cli_test_runner.php \
  --token=26beaf4519af68faa339f0eb58f0fe5d \
  --actor=18 \
  --tid=12775 \
  --action=all

# Only auth check
php modules/transfers/stock/testing/cli_test_runner.php --token=... --actor=18 --action=auth

# Status only
php modules/transfers/stock/testing/cli_test_runner.php --token=... --actor=18 --tid=12775 --action=status

# Finalize only
php modules/transfers/stock/testing/cli_test_runner.php --token=... --actor=18 --tid=12775 --action=finalize

# Send with shipment details
php modules/transfers/stock/testing/cli_test_runner.php --token=... --actor=18 --tid=12775 --action=send \
  --carrier=GSS --tracking=ABC123 --reference=T-12775

# Receive partial with items (CSV: SKU:QTY,SKU:QTY)
php modules/transfers/stock/testing/cli_test_runner.php --token=... --actor=18 --tid=12775 --action=receive_partial \
  --sku='SKU123:2,SKU456:1'

# Receive final with items
php modules/transfers/stock/testing/cli_test_runner.php --token=... --actor=18 --tid=12775 --action=receive_final \
  --sku='SKU123:3,SKU456:5'
```

## Output
- PASS/FAIL per step with request_id. Use request_id to correlate in logs.

## Notes
- Requires PHP CLI on the server and access to the web root so it can include `app.php` and the ajax handler.
- This bypasses CSRF and session like the curl header path, but avoids shell quoting issues.
- Keep token restricted to DEV/STAGE. Remove it from env after testing.
