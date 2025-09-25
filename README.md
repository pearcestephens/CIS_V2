# CIS v2 Application Layout

This repository captures the production layout for the CIS v2 PHP application that serves the staff portal.

## Directory map

- `core/` – Framework bootstrap, middleware, security hardening, routing helpers, and platform services.
- `assets/templates/` – Shared layout partials rendered through the CIS template engine.
- `modules/` – Feature modules including views, AJAX handlers, migrations, assets, and documentation.
- `cisv2.code-workspace` – VS Code workspace definition used by the engineering team.

## Git setup

1. Initialise the repository from this directory:
   ```
   git init
   git add .
   git commit -m "Initial import of CIS v2 application layout"
   ```
2. Point the repo at the authoritative remote before pushing:
   ```
   git remote add origin <ssh-or-https-url>
   git push -u origin main
   ```

## Notes

- The nested Git metadata previously inside `modules/` has been removed so that module sources now version with the rest of the platform.
- The root `.gitignore` covers vendor/runtime artefacts shared across modules.
- Keep all URLs absolute to the staff domain when wiring routes inside the application.
