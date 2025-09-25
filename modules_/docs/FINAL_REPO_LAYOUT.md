# Final Repository Layout (Target State)

This is the target structure after executing Phases A01–A30. It is organized for production PHP (PSR‑12), clean ingress via middleware, and modularized “Pack + Receive” with shared building blocks.

Docroot points to /public. All PHP endpoints route through controllers in /public or module AJAX handlers under /modules/.../ajax. Nothing in /storage is web‑exposed.

```
CIS/
├─ .editorconfig
├─ .gitignore
├─ composer.json                         # (optional, if using PSR-4 autoload)
├─ README.md
├─ .env.example                          # never commit real secrets
│
├─ public/                               # web server docroot
│  ├─ index.php                          # front controller (loads core/bootstrap)
│  ├─ modules.php                        # minimal router for /module/... -> templates
│  ├─ assets/                            # (served) static assets; symlink or copy from /assets/public
│  └─ healthz.php                        # simple 200 OK page
│
├─ core/
│  ├─ bootstrap.php                      # env, DB PDO, timezone, autoloaders
│  ├─ config.php                         # config($key,$default,$type) accessor
│  ├─ security.php                       # global headers: HSTS, XFO, nosniff, CSP (report-only first)
│  ├─ csrf.php                           # cis_csrf_or_json_400(), token helpers
│  ├─ error.php                          # global error/exception handler, json envelopes
│  ├─ auth.php                           # cis_current_user(), cis_require_login($roles)
│  ├─ ai.php                             # ai_stream() stub / helpers
│  └─ middleware/
│     ├─ kernel.php                      # mw_trace, mw_security_headers, mw_json_or_form_normalizer, mw_csrf_or_api_key,
│     │                                   # mw_validate_content_type, mw_content_length_limit, mw_rate_limit,
│     │                                   # (optional) mw_enforce_auth, mw_idempotency, mw_cors, mw_maintenance
│     └─ README.md
│
├─ modules/
│  └─ transfers/
│     ├─ _shared/
│     │  ├─ TransferHelper.php           # ID maps, resolveTransferItemId, validateParcelPlan
│     │  ├─ events.php                   # canonical event constants + emit_event()
│     │  └─ README.md
│     │
│     ├─ stock/                          # PACK module (shipping labels, parcels)
│     │  ├─ ajax/
│     │  │  └─ handler.php               # JSON-only; middleware pipeline; idempotency-ready
│     │  ├─ lib/
│     │  │  ├─ PackHelper.php            # calculateShipUnits, autoAttachIfEmpty, MVP+real label create
│     │  │  ├─ GoSweetSpotHelper.php     # real carrier adapter
│     │  │  ├─ NZPostHelper.php          # real carrier adapter
│     │  │  └─ ManifestBuilder.php       # daily manifest CSV/PDF generator
│     │  ├─ views/
│     │  │  ├─ pack.php                  # UI (Items pane, Parcels pane, notes)
│     │  │  └─ pack.meta.php             # title/breadcrumb/assets include
│     │  ├─ js/
│     │  │  └─ pack.js                   # FormData client; validate → label → readback; Idempotency-Key header
│     │  └─ css/
│     │     └─ pack.css
│     │
│     └─ receive/                        # RECEIVE module (scan & reconcile)
│        ├─ ajax/
│        │  └─ handler.php               # get_shipment, scan_or_select, save_receipt, list_discrepancies
│        ├─ lib/
│        │  └─ ReceiveHelper.php         # receipt upserts, discrepancy rules
│        ├─ views/
│        │  ├─ receive.php               # UI (scan bar, items table, parcels list)
│        │  └─ dashboard.php             # transfers overview (filters, chips, status)
│        ├─ js/
│        │  └─ receive.js                # scan/tracking handlers; hotkeys; toasts
│        └─ css/
│           └─ receive.css
│
├─ assets/
│  ├─ templates/
│  │  └─ cisv2/
│  │     ├─ html-header.php              # includes meta csrf, CSP policy, bootstrap/css
│  │     ├─ header.php
│  │     ├─ sidemenu.php
│  │     ├─ footer.php
│  │     └─ html-footer.php
│  │
│  ├─ services/
│  │  ├─ pipeline/
│  │  │  └─ monitor.php                  # {ok,window_minutes,metrics,errors}; p95 per action
│  │  └─ print/
│  │     ├─ next.php                     # agent pulls next job (auth via X-Print-Key)
│  │     └─ update.php                   # agent posts job status updates
│  │
│  ├─ public/                            # versioned static assets compiled/placed here
│  │  ├─ css/
│  │  ├─ js/
│  │  └─ img/
│  └─ README.md
│
├─ db/
│  ├─ migrations/
│  │  ├─ 001_transfer_indexes.sql        # idx_ti_tid_pid, idx_ts_tid, idx_tp_sid_box, idx_tpi_parcel/item, unique u_parcel_item
│  │  ├─ 002_transfer_fks.sql            # FKs for parcels, parcel_items → shipments/items
│  │  ├─ 003_idempotency_keys.sql
│  │  ├─ 004_receive_core.sql            # transfer_receipts, transfer_receipt_items, transfer_discrepancies
│  │  ├─ 005_outlet_tokens_orders.sql    # outlet_courier_tokens, transfer_carrier_orders
│  │  ├─ 006_print_jobs.sql
│  │  ├─ 007_container_rules.sql
│  │  ├─ 008_log_indexes.sql             # idx logs/audit by transfer_id+created_at
│  │  └─ 009_security_hardening.sql      # optional: permissions, views, etc.
│  ├─ seeds/
│  │  ├─ seed_container_rules.sql
│  │  ├─ seed_sample_transfer.sql
│  │  └─ seed_users_roles.sql
│  └─ migrate.php                         # tiny CLI runner (applies in order)
│
├─ tools/
│  ├─ rebuild_weight_cache.php            # precompute product → unit_g; chunked
│  ├─ recount_parcel_items.php            # (if you materialize counts)
│  ├─ request_inspector.php               # lookup by request_id; correlated logs
│  ├─ slow_sql.php                        # last N slow queries (normalized)
│  └─ README.md
│
├─ storage/                               # NOT web-served; set 750 and owned by www user
│  ├─ logs/
│  │  ├─ app.log
│  │  ├─ errors.log
│  │  ├─ profiling.log                    # cis_profile_flush() sink (or DB)
│  │  └─ rotate.conf                      # (if using logrotate)
│  ├─ labels/
│  │  └─ {shipment_id}/label_{box}.pdf    # persisted or cached label files
│  ├─ cache/
│  └─ tmp/
│
├─ agents/                                # optional local utilities (not deployed to server)
│  └─ print/
│     ├─ cis-print-agent.js               # Node-based reference agent (poll/print/update)
│     └─ README.md                        # setup on Windows/macOS/Linux
│
└─ config/
   ├─ csp-report-endpoint.php             # (optional) log CSP report-only violations
   └─ README.md
```

Quick notes & conventions
- Middleware pipeline is the only ingress for AJAX handlers.
- Observability: /assets/services/pipeline/monitor.php is the single JSON health source.
- Printing: queue-driven via /assets/services/print/* and a local CIS Print Agent.
- Migrations: small, ordered SQL files with a CLI runner.
- Security: CSP (report-only first), HSTS, XFO, nosniff, strict referrer, CSRF; optional CORS; secrets via .env only.
- Writable: only /storage/** is writable at runtime (labels, logs, cache, tmp).
