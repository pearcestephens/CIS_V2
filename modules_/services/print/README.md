# Print Service Endpoints

- Base: https://staff.vapeshed.co.nz/modules/services/print/
- next.php – Agent polls for next job (501 until wired)
- update.php – Agent posts job result (501 until wired)

Schema dependency: print_jobs (see migrations 2025-09-24_006_print_jobs.sql)

Security: Agents should authenticate via X-Print-Key; to be added when implementing.
