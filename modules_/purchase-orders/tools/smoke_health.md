# Purchase Orders – Health Smoke

Use cases:

- Health (GET, unauthenticated):
  - https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php?ajax_action=health

Expected 200 body:

```
{ "success": true, "data": { "module": "purchase-orders", "status": "healthy", "time": "2025-09-25T..Z" }, "request_id": "..." }
```

- Notes
  - JSON envelope should include request_id and security headers present (nosniff, referrer-policy).
  - CSRF remains required for mutating actions.
