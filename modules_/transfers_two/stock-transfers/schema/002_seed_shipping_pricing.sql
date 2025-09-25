-- 002_seed_shipping_pricing.sql
-- Seed carriers, services, containers, pricing rules, and surcharges based on provided table

START TRANSACTION;

-- Carriers
INSERT INTO carriers (code, name) VALUES
  ('NZ_POST', 'NZ Post')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO carriers (code, name) VALUES
  ('GSS', 'NZ Couriers (GSS)')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Helper variables
SET @nzpost_id = (SELECT carrier_id FROM carriers WHERE code='NZ_POST');
SET @gss_id    = (SELECT carrier_id FROM carriers WHERE code='GSS');

-- NZ Post services (letters and boxes)
INSERT INTO carrier_services (carrier_id, code, name) VALUES
  (@nzpost_id, 'LETTER', 'Standard Mail Letter'),
  (@nzpost_id, 'BOX',    'Post Box')
ON DUPLICATE KEY UPDATE name=VALUES(name);

SET @svc_letter = (SELECT service_id FROM carrier_services WHERE carrier_id=@nzpost_id AND code='LETTER');
SET @svc_box    = (SELECT service_id FROM carrier_services WHERE carrier_id=@nzpost_id AND code='BOX');

-- Letter containers
INSERT INTO containers (carrier_id, service_id, code, name, length_mm, width_mm, height_mm, max_weight_grams) VALUES
  (@nzpost_id, @svc_letter, 'MEDIUM_LETTER',   'Medium Letter (≤500g, 130×235×6mm)', 130,235,6, 500),
  (@nzpost_id, @svc_letter, 'LARGE_LETTER',    'Large Letter (≤500g, 165×235×10mm)', 165,235,10, 500),
  (@nzpost_id, @svc_letter, 'OVERSIZE_LETTER', 'Oversize Letter (≤1kg, 260×385×20mm)', 260,385,20, 1000)
ON DUPLICATE KEY UPDATE name=VALUES(name), length_mm=VALUES(length_mm), width_mm=VALUES(width_mm), height_mm=VALUES(height_mm), max_weight_grams=VALUES(max_weight_grams);

-- Box containers
INSERT INTO containers (carrier_id, service_id, code, name, length_mm, width_mm, height_mm) VALUES
  (@nzpost_id, @svc_box, 'BOX_1',   'Box Size 1 (235×165×70 mm)', 235,165,70),
  (@nzpost_id, @svc_box, 'BOX_2',   'Box Size 2 (250×185×170 mm)', 250,185,170),
  (@nzpost_id, @svc_box, 'BOX_3',   'Box Size 3 (350×265×200 mm)', 350,265,200),
  (@nzpost_id, @svc_box, 'BOX_4',   'Box Size 4 (318×216×507 mm)', 318,216,507),
  (@nzpost_id, @svc_box, 'BOX_5',   'Box Size 5 (418×286×540 mm)', 418,286,540),
  (@nzpost_id, @svc_box, 'BOX_WINE','Wine Box (460×140×120 mm)',  460,140,120)
ON DUPLICATE KEY UPDATE name=VALUES(name), length_mm=VALUES(length_mm), width_mm=VALUES(width_mm), height_mm=VALUES(height_mm);

-- NZ Post pricing
INSERT INTO pricing_rules (carrier_id, service_id, container_id, price)
SELECT @nzpost_id, @svc_letter, c.container_id, p.price FROM (
  SELECT 'MEDIUM_LETTER' code, 2.90 price UNION ALL
  SELECT 'LARGE_LETTER',  4.20 UNION ALL
  SELECT 'OVERSIZE_LETTER', 5.50
) p JOIN containers c ON c.code=p.code AND c.carrier_id=@nzpost_id
ON DUPLICATE KEY UPDATE price=VALUES(price);

INSERT INTO pricing_rules (carrier_id, service_id, container_id, price)
SELECT @nzpost_id, @svc_box, c.container_id, p.price FROM (
  SELECT 'BOX_1' code, 2.50 price UNION ALL
  SELECT 'BOX_2', 4.50 UNION ALL
  SELECT 'BOX_3', 5.00 UNION ALL
  SELECT 'BOX_4', 6.50 UNION ALL
  SELECT 'BOX_5', 8.50 UNION ALL
  SELECT 'BOX_WINE', 5.00
) p JOIN containers c ON c.code=p.code AND c.carrier_id=@nzpost_id
ON DUPLICATE KEY UPDATE price=VALUES(price);

-- GSS tickets as services (flat)
INSERT INTO carrier_services (carrier_id, code, name) VALUES
  (@gss_id, 'LOCAL',        'Local Delivery (≤0.1 m³ or 25kg)'),
  (@gss_id, 'OUTER_AREA',   'Outer Area (≤15kg or 0.025 m³)'),
  (@gss_id, 'SHORTHAUL',    'Shorthaul (≤15kg or 0.025 m³)'),
  (@gss_id, 'LONGHAUL',     'Longhaul (≤5kg or 0.025 m³)'),
  (@gss_id, 'INTER_ISLAND', 'Inter-Island (≤5kg or 0.025 m³)')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- GSS pricing (per service)
INSERT INTO pricing_rules (carrier_id, service_id, container_id, price)
SELECT @gss_id, s.service_id, NULL, p.price FROM (
  SELECT 'LOCAL' code, 11.50 price UNION ALL
  SELECT 'OUTER_AREA', 12.00 UNION ALL
  SELECT 'SHORTHAUL',  15.00 UNION ALL
  SELECT 'LONGHAUL',   21.00 UNION ALL
  SELECT 'INTER_ISLAND', 38.00
) p JOIN carrier_services s ON s.code=p.code AND s.carrier_id=@gss_id
ON DUPLICATE KEY UPDATE price=VALUES(price);

-- GSS surcharges
INSERT INTO surcharges (carrier_id, code, name, price) VALUES
  (@gss_id, 'RURAL',       'Rural Delivery',                   8.00),
  (@gss_id, 'SATURDAY',    'Saturday Delivery',                8.00),
  (@gss_id, 'RESIDENTIAL', 'Residential Delivery Zone',        3.50),
  (@gss_id, 'R18',         'R18 Restricted Delivery',          6.80)
ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price);

COMMIT;
