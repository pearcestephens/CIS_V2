# Modules Documentation Index

Central index for module-level documentation sets.

## Structure & Standards
- Each module should maintain a `docs/` folder with:
  - `REFERENCE_MAP.md` – DOM IDs, CSS classes, JS entry points, AJAX routes
  - `COMPONENT_INDEX.md` – reusable UI components with markup + hooks
  - `KNOWLEDGE_BASE.md` – architecture, flows, conventions, perf/security notes
  - `CHANGELOG.md` – dated changes to the module and/or docs
- Keep docs close to code. Update docs in the same PR as code changes.

## Available Docs
- Stock Transfers: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/`
  - Reference Map: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/REFERENCE_MAP.md`
  - Component Index: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/COMPONENT_INDEX.md`
  - Knowledge Base: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/KNOWLEDGE_BASE.md`
  - Changelog: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/CHANGELOG.md`
  - Quickstart: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/PACK_QUICKSTART.md`
  - API Contract: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/PACK_API_CONTRACT.md`
- Purchase Orders: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/`
  - Reference Map: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/REFERENCE_MAP.md`
  - Component Index: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/COMPONENT_INDEX.md`
  - Knowledge Base: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/KNOWLEDGE_BASE.md`
  - Changelog: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/CHANGELOG.md`

Additional Docs
- Transfers (base):
  - Overview: `https://staff.vapeshed.co.nz/modules/transfers/README.md`
  - Design: `https://staff.vapeshed.co.nz/modules/transfers/DESIGN.md`
- Audit Viewer: `https://staff.vapeshed.co.nz/modules/audit/README.md`
- Migrations: `https://staff.vapeshed.co.nz/modules/migrations/README.md`
- Scaffold Template: `https://staff.vapeshed.co.nz/modules/MODULE_TEMPLATE/README.md`
- Global Modules Guide: `https://staff.vapeshed.co.nz/modules/README.md`

Consolidated Knowledge Base Snapshot
- `https://staff.vapeshed.co.nz/modules/docs/KNOWLEDGE_BASE_SUMMARY.md`
- Phases Plan (A30→A01): `https://staff.vapeshed.co.nz/modules/docs/PHASES_A30_TO_A01.md`
- Final Repo Layout (Target): `https://staff.vapeshed.co.nz/modules/docs/FINAL_REPO_LAYOUT.md`

Freight, Packaging, Categorisation
- Freight & Categorisation Bible v1: `https://staff.vapeshed.co.nz/modules/docs/Freight,Weight.md`

## Contributing
- Follow PSR-12 for code; keep CSS/JS under 25KB per file where possible.
- No secrets in docs. Use absolute URLs under `https://staff.vapeshed.co.nz` when referencing assets or views.
- When adding a new module, copy the 4-file doc pattern and link it here.
 - Keep the consolidated snapshot updated after significant changes.
 - Use absolute URLs under https://staff.vapeshed.co.nz in all docs.

Printer Tokens Policy (Transfers)
- Carrier availability chips now reflect tokens found only in vend_outlets for the active outlet; environment/wrapper fallbacks have been removed from signalling.
