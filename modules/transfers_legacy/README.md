# Transfers (Base)

This is the unified transfers module base. Specialized transfer types (stock, juice, in-store) inherit the base patterns and helpers.

- Base helpers in `base/` (see `base/model.php` for unified types and status)
- Stock transfers live in `modules/transfers/stock/`
- Top-level dashboard: https://staff.vapeshed.co.nz/modules/transfers/dashboard.php
- Stock dashboard: https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php
- Pack (via CIS Template): https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=pack&transfer={id}
- Outgoing (via CIS Template): https://staff.vapeshed.co.nz/modules/module.php?module=transfers/stock&view=outgoing

Usage: include `modules/_shared/template.php` in views and call base `init.php` when you need base facilities. All documentation and links must use absolute https://staff.vapeshed.co.nz URLs per org policy.

Printer/Pack Final Form (2025-09-24)
- The printer page now presents a summarized final form (header, Summary, Delivery Method with Box Slips, Notes, Shipping & Labels, Finalise). See stock docs for details.

Docs
- Stock docs index: https://staff.vapeshed.co.nz/modules/transfers/stock/docs/

See DESIGN.md for the unified model and lifecycle, and the Purchase Orders exception (supplier party).
