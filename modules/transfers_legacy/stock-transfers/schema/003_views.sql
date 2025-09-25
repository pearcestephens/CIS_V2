-- 003_views.sql
-- Reporting/compatibility views

START TRANSACTION;

-- Flattened pricing matrix for easy querying
CREATE OR REPLACE VIEW pricing_matrix AS
SELECT
  pr.rule_id,
  c.code  AS carrier_code,
  c.name  AS carrier_name,
  s.code  AS service_code,
  s.name  AS service_name,
  co.code AS container_code,
  co.name AS container_name,
  co.length_mm,
  co.width_mm,
  co.height_mm,
  co.max_weight_grams,
  pr.price,
  pr.currency,
  pr.effective_from,
  pr.effective_to,
  pr.created_at,
  pr.updated_at
FROM pricing_rules pr
JOIN carriers c           ON c.carrier_id = pr.carrier_id
LEFT JOIN carrier_services s ON s.service_id = pr.service_id
LEFT JOIN containers co       ON co.container_id = pr.container_id;

-- Legacy compatibility projection similar to freight_rules
CREATE OR REPLACE VIEW freight_rules_compat AS
SELECT
  COALESCE(co.name, CONCAT(c.name, ' ', COALESCE(s.name, ''))) AS container,
  co.max_weight_grams,
  NULL AS max_units,
  pr.price AS cost,
  pr.created_at,
  pr.updated_at
FROM pricing_rules pr
JOIN carriers c             ON c.carrier_id = pr.carrier_id
LEFT JOIN carrier_services s  ON s.service_id = pr.service_id
LEFT JOIN containers co        ON co.container_id = pr.container_id;

COMMIT;
