Freight and Categorisation Bible v1
Audience: bots first, humans second
Purpose: a single, operationally complete reference to understand, integrate, extend, and safely change the freight, packaging, and product categorisation model. Purely deterministic, production safe, CI friendly.

-------------------------------------------------------------------------------
TABLE OF CONTENTS
-------------------------------------------------------------------------------
0) Executive summary
1) Core entities and relationships
2) Table definitions that matter
3) Constraints and indexes
4) Seed data currently implemented
5) Views and why they exist
6) Core functions and contracts
7) Container decision logic notes
8) Operational playbook queries
9) Data quality and CI checks
10) Extensibility patterns
11) Gotchas and footguns
12) Quick reference cheat sheet
Nice-to-have hardening
Bottom line status
A) Categorisation pipeline
B) Packaging model and algorithms
C) Seeding logic (new or reset stores)
D) Freight plus packaging end-to-end
E) Ops checklists for tuning
F) Future-proofing hooks
G) Remaining content work
Views and dashboards to keep or drop
Safe drop pattern
Quick metadata helpers
TLDR keepers

-------------------------------------------------------------------------------
0) EXECUTIVE SUMMARY
-------------------------------------------------------------------------------
What this does
- Normalises vendor categories into a CIS taxonomy and derives unit weight and pack rules.
- Models carriers to services to containers to freight rules including cost and limits.
- Provides helpers to pick the best container, explain the decision, and price a product line deterministically.
- Adds delivery options per service such as Signature, ATL, Age-Restricted, Photo, Return.
- Ships with views and health checks so regressions are caught early.

High level flow
- Product maps to CIS category via vend_category_map.
- Unit weight is product avg_weight_grams or category fallback.
- Using shipment dims and weight, choose a container by capacity, dims, and cost.
- Price from freight_rules for the chosen container.

-------------------------------------------------------------------------------
1) CORE ENTITIES AND RELATIONSHIPS
-------------------------------------------------------------------------------
Taxonomy and products
- vend_products (PK id) are master products. May include avg_weight_grams.
- vend_categories represent the raw Lightspeed vendor category tree.
- vend_category_map connects vend_category_id to categories.id.
- categories (PK id) is the CIS canonical taxonomy.
- product_categorization_data is staging. Holds per product vendor category and resolved CIS category, if known.
- product_classification_unified is one row per product with canonical CIS category, product type code, confidence, and reasoning.

Weights and pack rules
- category_weights provide default weight per CIS category when product data is missing.
- category_pack_rules set default pack info per CIS category.
- pack_rules apply optional overrides at product or category scope.

Freight model
- carriers includes NZ Post, NZ Couriers or GSS, and others.
- carrier_services include specific services like NZ Post Domestic Courier, Express Tonight, or GSS Standard, Express.
- containers define carrier or service scoped SKUs such as DLE, A5, A4, Boxes, and GSS packs E20, E25b, E40, E50, E60, PP, DP.
- containers include kind, dimensions, and max weight.
- freight_rules link one to one with containers and define cost and rule caps such as weight and units.

Delivery options
- delivery_options is the master list such as Signature Required, No ATL, Age-Restricted, Photo, Create Return.
- carrier_service_options defines which options are allowed per service.

-------------------------------------------------------------------------------
2) TABLE DEFINITIONS THAT MATTER
-------------------------------------------------------------------------------
2.1 Taxonomy and products
- vend_products(id VARCHAR(100) PK, avg_weight_grams INT NULL, ...)
- vend_categories(categoryID VARCHAR(50) UNIQUE, name, ...)
- categories(id VARCHAR(50) PK, name, parent_id, depth, lft, rgt, slug, ...)
- vend_category_map(vend_category_id VARCHAR(50) PK, target_category_id VARCHAR(50) NOT NULL, refinement_status ENUM('unknown','mapped','refined') DEFAULT 'mapped')
  Foreign key: target_category_id references categories(id)
- product_categorization_data(product_id VARCHAR(100), lightspeed_category_id VARCHAR(50), category_id VARCHAR(50) NULL, ...)
  Foreign keys: product_id references vend_products(id), category_id references categories(id)
- product_classification_unified(product_id VARCHAR(100) PK, product_type_code VARCHAR(..) NOT NULL, category_id VARCHAR(50) NOT NULL, confidence INT, method, reasoning, ...)
  Foreign keys: product_id references vend_products(id), category_id references categories(id)

2.2 Weights and pack rules
- category_weights(category_id VARCHAR(50), avg_weight_grams INT, ...)
  Prefer category_id, not text codes.
- category_pack_rules(category_id VARCHAR(50), default_pack_size, default_outer_multiple, ...)
- pack_rules(scope ENUM('product','category'), scope_id VARCHAR(50), pack_size, outer_multiple, ...)
  Optionally split into pack_rules_product and pack_rules_category for strict foreign keys.

2.3 Freight
- carriers(carrier_id INT PK, code, name, active)
- carrier_services(service_id INT PK, carrier_id INT, code, name)
  Foreign key: carrier_id references carriers(carrier_id)
- containers(container_id INT PK, carrier_id INT NOT NULL, service_id INT NULL, code VARCHAR(64) NOT NULL, name, kind ENUM('bag','box','document','unknown') DEFAULT 'unknown', length_mm, width_mm, height_mm, max_weight_grams, max_units, UNIQUE (carrier_id, code))
  Foreign keys: carrier_id references carriers, service_id references carrier_services
- freight_rules(container_id INT PK, container VARCHAR(50) UNIQUE, max_weight_grams INT NULL, max_units INT NULL, cost DECIMAL(10,2) NOT NULL CHECK(cost >= 0), created_at, updated_at)
  Foreign key: container_id references containers(container_id)
  Indexes: container_id is PK, ix_fr_container_id, ix_fr_cap_cost(max_weight_grams, cost), ix_fr_cost(cost)

2.4 Delivery options
- delivery_options(option_code VARCHAR(32) PK, name, description)
- carrier_service_options(service_id INT, option_code VARCHAR(32), PRIMARY KEY(service_id, option_code))
  Foreign keys: service_id references carrier_services, option_code references delivery_options

-------------------------------------------------------------------------------
3) CONSTRAINTS AND INDEXES
-------------------------------------------------------------------------------
- carrier_services.carrier_id foreign key references carriers(carrier_id)
- containers.carrier_id foreign key references carriers(carrier_id)
- containers.service_id foreign key references carrier_services(service_id) and is nullable
- containers unique constraint on (carrier_id, code) to deduplicate per carrier
- containers recommended indexes: ix_containers_kind(kind), ix_containers_code(code), ix_containers_dims(kind, length_mm, width_mm, height_mm), ix_containers_carrier_service(carrier_id, service_id)
- freight_rules.container_id primary key and foreign key to containers(container_id), uq_fr_container(container) for legacy text uniqueness
- freight_rules recommended indexes: ix_fr_cap_cost(max_weight_grams, cost), ix_fr_cost(cost)
- vend_category_map.target_category_id foreign key to categories(id)
- product_classification_unified.product_id foreign key to vend_products(id)
- product_classification_unified.category_id foreign key to categories(id)
- product_categorization_data.product_id foreign key to vend_products(id)
- product_categorization_data.category_id foreign key to categories(id)

-------------------------------------------------------------------------------
4) SEED DATA CURRENTLY IMPLEMENTED
-------------------------------------------------------------------------------
NZ Post carrier_id 1
- Services: DOM_COURIER, DOM_EXP_TONIGHT
- Containers labels and bags: DLE, A5, A5B, A4, A4B, FS, LF, XL
- Containers boxes and letters: Bag, Small Box, Medium Box, Large Box, PARCEL, BOX_1 to BOX_5, BOX_WINE, MEDIUM_LETTER, LARGE_LETTER, OVERSIZE_LETTER
- Costs: filled per single bag range RRP, boxes and letters derived from small medium large anchors, caps synced

NZ Couriers or GSS carrier_id 2
- Services: NZC_STANDARD, NZC_EXPRESS
- Containers: E20, E25b, E40, E50, E60, PP, DP, BOX
- Costs: baseline aligned to similar NZ Post sizes, caps 15000 grams for packs, 25000 grams for boxes

Delivery options for both carriers
- SIGNATURE_REQUIRED, NO_ATL, AGE_RESTRICTED, PHOTO_REQUIRED, CREATE_RETURN
- Allowed per service via carrier_service_options

-------------------------------------------------------------------------------
5) VIEWS AND WHY THEY EXIST
-------------------------------------------------------------------------------
- v_classification_coverage shows mapped versus unknown counts for product_classification_unified
- v_weight_coverage shows coverage of unit weight from product to category to missing
- v_freight_rules_catalog snapshots carrier to container to rule cost and caps
- v_nzpost_eship_containers lists NZ Post eShip catalogue for UI, bags and parcel plus letters
- v_carrier_container_prices is the authoritative current pricebook with carrier, service, container, caps, and cost
- Optional views for CI alarms include v_zero_or_null_prices and v_missing_container_rules

-------------------------------------------------------------------------------
6) CORE FUNCTIONS AND CONTRACTS
-------------------------------------------------------------------------------
pick_container_id(in_carrier_id, in_length_mm, in_width_mm, in_height_mm, in_weight_g) returns INT
- Computes volumetric grams using length times width times height in cubic meters times volumetric factor times 1000. Use factor 200 if not set per carrier.
- If any dimension is zero or null, volumetric is zero.
- Required weight equals max of actual grams and volumetric grams.
- Filter to containers for that carrier where freight_rules.max_weight_grams covers required or is null for unlimited.
- If the container has dimensions, they must fit. Bags often store height as zero so skip that dimension if zero.
- Returns the cheapest valid container. Ties break on lower cap then lower id.

pick_container_json(...) returns JSON
- Wraps pick_container_id and returns container id, code, name, kind, dims, cap, cost, carrier.

pick_container_cost(...) returns DECIMAL(10,2)
- Returns the money cost for the chosen container.

pick_container_explain_json(...) returns JSON
- Returns inputs, computed volumetric and required grams, top 3 candidates with fit booleans and costs, and the picked container. Use for debugging and QA.

price_line_json(in_product_id, in_qty, in_carrier_id) returns JSON
- Resolves unit weight using product.avg_weight_grams then category_weights then default 100 grams.
- Computes total grams from quantity and calls pick_container_json with weight only and null dimensions.
- Returns JSON with input, weights, and chosen container.

price_line_cost(in_product_id, in_qty, in_carrier_id) returns DECIMAL(10,2)
- Same logic as price_line_json but returns cost only.

Performance notes
- Index freight_rules on (max_weight_grams, cost) to accelerate picks.
- Index containers on (carrier_id, code), on kind, and on dimension composite for dimension fits.

-------------------------------------------------------------------------------
7) CONTAINER DECISION LOGIC NOTES
-------------------------------------------------------------------------------
- Volumetric: when all dims are present and greater than zero, volumetric kilograms equals (L x W x H in cubic meters) x volumetric factor 200. Otherwise skip volumetric.
- Required weight equals max(actual grams, volumetric grams).
- Fit rules: container max_weight_grams if set must cover required grams. Dimensions if set must be greater than or equal to item dimensions.
- Cheapest wins with tie break on lower cap then id.
- Kind preference: when height is greater than zero and dims are large, prefer kind in box or document over bag.
- Delivery option constraints are applied at booking time, for example age restricted might force signature.
- Enforce sanity such as bag caps less than or equal to 15000 grams and health checks after inserts.

-------------------------------------------------------------------------------
8) OPERATIONAL PLAYBOOK QUERIES
-------------------------------------------------------------------------------
Unmapped vendor categories
SELECT pcd.lightspeed_category_id, COUNT(*)
FROM product_categorization_data pcd
LEFT JOIN vend_category_map vcm ON vcm.vend_category_id = pcd.lightspeed_category_id
WHERE vcm.target_category_id IS NULL
GROUP BY 1 ORDER BY 2 DESC;

Products missing classification row
SELECT vp.id, vp.name
FROM vend_products vp
LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
WHERE pcu.product_id IS NULL;

Containers without a rule
SELECT c.*
FROM containers c
LEFT JOIN freight_rules fr ON fr.container_id = c.container_id
WHERE fr.container_id IS NULL;

Current catalogue by carrier
SELECT * FROM v_carrier_container_prices;

Price a line by weight only
SELECT price_line_cost('<product_id>', 3, 1);

Explain a pick for QA
SELECT pick_container_explain_json(2, 415, 360, 0, 14000);

-------------------------------------------------------------------------------
9) DATA QUALITY AND CI CHECKS
-------------------------------------------------------------------------------
Zero or null prices
SELECT *
FROM freight_rules fr
WHERE fr.cost IS NULL OR fr.cost = 0.00;

Rule cap greater than container cap
SELECT fr.*
FROM freight_rules fr
JOIN containers c ON c.container_id = fr.container_id
WHERE c.max_weight_grams IS NOT NULL
  AND (fr.max_weight_grams IS NULL OR fr.max_weight_grams > c.max_weight_grams);

Unknown container kind
SELECT * FROM containers WHERE kind = 'unknown';

Products missing any weight
SELECT pcu.product_id
FROM product_classification_unified pcu
LEFT JOIN vend_products vp ON vp.id = pcu.product_id
LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
WHERE vp.avg_weight_grams IS NULL AND cw.avg_weight_grams IS NULL;

-------------------------------------------------------------------------------
10) EXTENSIBILITY PATTERNS
-------------------------------------------------------------------------------
Add a new carrier
- Insert into carriers
- Seed carrier_services
- Seed containers including codes, dims, caps
- Insert freight_rules
- Rebuild views and run health checks

Add a new pack container such as NZ Post A2
- Insert into containers with kind, dims, caps
- Insert into freight_rules with cost
- Refresh v_carrier_container_prices

Service specific containers
- Set containers.service_id. Null means general. Pick logic continues to work.

Zoned or scheduled pricing
- Add columns to freight_rules for zone_code and effective_from or effective_to
- Include these in pick filters or introduce a pricing_rules table keyed by carrier_id, service_id, container_id, zone, and date

Different volumetric factors
- Add carriers.volumetric_factor default 200 and update pick_container_id to use it

-------------------------------------------------------------------------------
11) GOTCHAS AND FOOTGUNS
-------------------------------------------------------------------------------
- Bag labels on boxes. NZ Post forbids using courier pack labels for boxes. UI should warn when kind is bag but dims or weight imply a box.
- Null dims. If dims are null or zero we treat as dimensionless and only weight matters. Provide dims to trigger volumetric pricing.
- Multiple category ids. Standardise on categories.id everywhere. Avoid text slugs in joins.
- Freight rule orphans. Keep PK on freight_rules(container_id) and FK to containers to stay safe.

-------------------------------------------------------------------------------
12) QUICK REFERENCE CHEAT SHEET
-------------------------------------------------------------------------------
Functions
- pick_container_id(carrier_id, Lmm, Wmm, Hmm, grams) returns container_id
- pick_container_cost(...) returns DECIMAL
- pick_container_json(...) returns JSON
- pick_container_explain_json(...) returns JSON with top 3 candidates and reasons
- price_line_cost(product_id, qty, carrier_id) returns DECIMAL
- price_line_json(product_id, qty, carrier_id) returns JSON

Authoritative catalogue for UI
- SELECT * FROM v_carrier_container_prices;

Health spot checks
- Unknown kinds in containers
- Zero or null prices in freight_rules
- Rule cap greater than container cap
- Missing weight fallbacks resolved via vend_products or category_weights

What is in place yes
- Foreign keys for category and product classification and staging are set
- Containers to carriers and service ids are set
- Freight rules to containers are enforced with PK on container_id
- Indexes on freight_rules and containers exist as described
- Views including v_classification_coverage, v_weight_coverage, v_freight_rules_catalog, v_nzpost_eship_containers, v_carrier_container_prices are present
- Functions pick_container_* and price_line_* exist
- Seeds for NZ Post and NZ Couriers or GSS and delivery options exist

-------------------------------------------------------------------------------
NICE TO HAVE HARDENING
-------------------------------------------------------------------------------
Make classification category non null now that backfill is done
ALTER TABLE product_classification_unified
  MODIFY category_id VARCHAR(50) NOT NULL;

Do the same for staging if every row has a map now
ALTER TABLE product_categorization_data
  MODIFY category_id VARCHAR(50) NOT NULL;

Ensure mapping key is indexed
ALTER TABLE vend_category_map
  ADD INDEX ix_vcm_vendor (vend_category_id),
  ADD INDEX ix_vcm_target (target_category_id);

Speed up category joins
ALTER TABLE product_classification_unified ADD INDEX ix_pcu_category (category_id);
ALTER TABLE product_categorization_data  ADD INDEX ix_pcd_category (category_id);

Fast path for product weight lookups
ALTER TABLE vend_products ADD INDEX ix_vp_weight (avg_weight_grams);

Tiny audits to answer what is left
How many products still Unknown
SELECT
  SUM(pcu.category_id = '00000000-0000-0000-0000-000000000000') AS unknown_products,
  COUNT(*) AS total_products,
  ROUND(100 * SUM(pcu.category_id = '00000000-0000-0000-0000-000000000000') / COUNT(*), 2) AS unknown_pct
FROM product_classification_unified pcu;

Which vendor categories feed Unknown
SELECT pcd.lightspeed_category_id, COUNT(*) AS products
FROM product_categorization_data pcd
JOIN product_classification_unified pcu ON pcu.product_id = pcd.product_id
WHERE pcu.category_id = '00000000-0000-0000-0000-000000000000'
GROUP BY 1 ORDER BY products DESC LIMIT 50;

Missing unit weights where product and category are both null
SELECT COUNT(*) AS missing_weight
FROM product_classification_unified pcu
LEFT JOIN vend_products vp ON vp.id = pcu.product_id
LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
WHERE vp.avg_weight_grams IS NULL AND cw.avg_weight_grams IS NULL;

-------------------------------------------------------------------------------
BOTTOM LINE STATUS
-------------------------------------------------------------------------------
Schemas, foreign keys, indexes, views, and functions are up to date based on the work described.
Open item is content. Some products remain Unknown until assigned a CIS category and weight. System handles this with safe fallbacks.

-------------------------------------------------------------------------------
A) CATEGORISATION PIPELINE
-------------------------------------------------------------------------------
A.1 Canonical taxonomy and mapping
Goal: each product has one CIS category categories.id that downstream systems can trust.

Deterministic pipeline
- product_categorization_data.lightspeed_category_id from Vend
- Map via vend_category_map vend_category_id to categories.id
- Promote to product_classification_unified with method and reasoning fields

Confidence and provenance
- method is one of system mapped, model ML, manual ops fixed
- confidence range is 0 to 100, treat less than 50 as needs review

Audits
Count Unknown products
SELECT COUNT(*) FROM product_classification_unified
WHERE category_id = '00000000-0000-0000-0000-000000000000';

Top vendor categories producing Unknown
SELECT pcd.lightspeed_category_id, COUNT(*) c
FROM product_categorization_data pcd
JOIN product_classification_unified pcu USING (product_id)
WHERE pcu.category_id = '00000000-0000-0000-0000-000000000000'
GROUP BY 1 ORDER BY c DESC LIMIT 20;

A.2 Category signals for logistics
- Hazmat and restricted flags such as nicotine salts and batteries as boolean flags on categories
- Fragility for glass tanks drives box only policy and min padding
- Temperature sensitivity note for future cold chain rules
- Freight sensitivity mapping category to default pack size, outer multiple, and avg weight

-------------------------------------------------------------------------------
B) PACKAGING MODEL AND ALGORITHMS
-------------------------------------------------------------------------------
B.1 Why it matters
- Packaging hierarchy converts sellable units into freightable units and affects cost
- Some products must ship in outers due to leaks, spills, glass, aerosols
- Outer multiples reduce damages and chargeable tickets

B.2 Data model minimal and powerful

Tables already present
- pack_rules with precedence product over category and values for pack_size and outer_multiple
- category_pack_rules with defaults per category

Recommended additions

1) Master carton specs by product or category
CREATE TABLE IF NOT EXISTS carton_specs (
  scope ENUM('product','category') NOT NULL,
  scope_id VARCHAR(50) NOT NULL,
  units_per_carton INT NOT NULL,
  carton_length_mm INT NULL,
  carton_width_mm  INT NULL,
  carton_height_mm INT NULL,
  carton_weight_g  INT NULL,
  is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (scope, scope_id)
);

2) Packaging policy flags
ALTER TABLE pack_rules
  ADD COLUMN must_outer_pack TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN min_outer_multiple INT NULL,
  ADD COLUMN max_units_per_pack INT NULL;

3) Retail or inner pack versus ship pack distinction
- Keep default_pack_size for retail inner
- Add ship_pack_min so freight can round up when fragile
ALTER TABLE category_pack_rules
  ADD COLUMN ship_pack_min INT NULL;

B.3 Precedence rules
- Pack size uses pack_rules for product, then pack_rules for category, then category_pack_rules.default_pack_size, then 1
- Outer multiple uses the same precedence
- If must_outer_pack is 1, enforce ceiling to the nearest outer_multiple
- If carton is mandatory, convert to full cartons

Helper view consolidation
CREATE OR REPLACE VIEW v_product_pack_profile AS
SELECT
  pcu.product_id,
  pcu.category_id,
  COALESCE(pr_prod.pack_size, pr_cat.pack_size, cpr.default_pack_size, 1) AS pack_size,
  COALESCE(pr_prod.outer_multiple, pr_cat.outer_multiple, cpr.default_outer_multiple, 1) AS outer_multiple,
  COALESCE(pr_prod.must_outer_pack, pr_cat.must_outer_pack, 0) AS must_outer_pack,
  COALESCE(cpr.ship_pack_min, 1) AS ship_pack_min,
  cs.units_per_carton,
  cs.is_mandatory AS carton_mandatory
FROM product_classification_unified pcu
LEFT JOIN pack_rules pr_prod ON pr_prod.scope = 'product' AND pr_prod.scope_id = pcu.product_id
LEFT JOIN pack_rules pr_cat  ON pr_cat.scope  = 'category' AND pr_cat.scope_id = pcu.category_id
LEFT JOIN category_pack_rules cpr ON cpr.category_id = pcu.category_id
LEFT JOIN carton_specs cs
  ON (cs.scope = 'product'  AND cs.scope_id = pcu.product_id)
  OR (cs.scope = 'category' AND cs.scope_id = pcu.category_id);

B.4 Turning quantity into ship units

Algorithm steps
- eff_pack is max(pack_size, ship_pack_min)
- q1 equals ceil(qty divided by eff_pack)
- if must_outer_pack then round q1 up to nearest outer_multiple
- q2 starts as q1
- if carton_mandatory then q2 equals ceil(q1 divided by units_per_carton) times units_per_carton
- return q2

SQL helper function
DELIMITER //
CREATE OR REPLACE FUNCTION calc_ship_units(
  in_qty INT, in_pack_size INT, in_ship_pack_min INT,
  in_outer_multiple INT, in_must_outer TINYINT(1),
  in_units_per_carton INT, in_carton_mandatory TINYINT(1)
) RETURNS INT
DETERMINISTIC
BEGIN
  DECLARE q1 INT; DECLARE q2 INT; DECLARE eff_pack INT;
  SET eff_pack = GREATEST(COALESCE(in_pack_size,1), COALESCE(in_ship_pack_min,1));
  SET q1 = CEIL(GREATEST(in_qty,0) / eff_pack);

  IF in_must_outer = 1 AND COALESCE(in_outer_multiple,1) > 1 THEN
    SET q1 = CEIL(q1 / in_outer_multiple) * in_outer_multiple;
  END IF;

  SET q2 = q1;
  IF in_carton_mandatory = 1 AND COALESCE(in_units_per_carton,0) > 0 THEN
    SET q2 = CEIL(q1 / in_units_per_carton) * in_units_per_carton;
  END IF;

  RETURN q2;
END//
DELIMITER ;

Audit to find painful skus
SELECT pcu.product_id, vp.name, vpp.pack_size, vpp.outer_multiple, vpp.units_per_carton
FROM v_product_pack_profile vpp
JOIN product_classification_unified pcu USING (product_id)
JOIN vend_products vp ON vp.id = pcu.product_id
WHERE (vpp.must_outer_pack = 1 AND (vpp.outer_multiple IS NULL OR vpp.outer_multiple <= 1))
   OR (vpp.carton_mandatory = 1 AND vpp.units_per_carton IS NULL);

-------------------------------------------------------------------------------
C) SEEDING LOGIC NEW OR RESET STORES
-------------------------------------------------------------------------------
C.1 Definition
- Seeding is the initial stocking of a new or emptied store using company wide inventory, sales velocity, and fair share goals
- Breadth first for coverage and then depth within capacity and freight efficiency

C.2 Inputs and knobs
- Sales velocity rolling 4 to 8 weeks excluding promos
- Coverage target per family
- Safety buffer number of days of demand with category specific values
- Store archetype multiplier kiosk versus destination
- Freight envelope limits for cartons or tickets per run

C.3 Algorithm high level
- Build candidate list from top movers and filter banned categories
- Per product target quantity equals round up velocity times buffer days over 7, then clamp by pack rules
- Greedy allocation from donors with highest surplus to the target store while obeying donor minimums and freight envelope
- Prefer full cartons near thresholds

Pre allocation view stub
CREATE OR REPLACE VIEW v_seed_targets AS
SELECT
  pcu.product_id,
  pcu.category_id,
  1 AS weekly_vel,
  14 AS buffer_days
FROM product_classification_unified pcu
WHERE pcu.category_id <> '00000000-0000-0000-0000-000000000000';

Application steps
- Join v_product_pack_profile and weekly velocity
- Compute target qty then ship_units equals calc_ship_units
- Convert ship_units times unit_weight grams into a picked container and price via pick_container_*

-------------------------------------------------------------------------------
D) FREIGHT PLUS PACKAGING END TO END
-------------------------------------------------------------------------------
D.1 Line pricing with packaging
Steps
1. Get packaging profile for product
2. ship_units equals calc_ship_units for the order qty and policy
3. total weight equals ship_units times product or category weight
4. container equals pick_container_id(carrier, dims optional, weight grams)
5. price equals freight_rules.cost for that container

Example compact
SELECT
  pcu.product_id,
  vpp.pack_size, vpp.outer_multiple, vpp.units_per_carton,
  calc_ship_units(12, vpp.pack_size, vpp.ship_pack_min, vpp.outer_multiple, vpp.must_outer_pack,
                  vpp.units_per_carton, vpp.carton_mandatory) AS ship_units,
  pick_container_cost(
    1,
    NULL, NULL, NULL,
    calc_ship_units(12, vpp.pack_size, vpp.ship_pack_min, vpp.outer_multiple, vpp.must_outer_pack,
                    vpp.units_per_carton, vpp.carton_mandatory) * COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 100)
  ) AS freight_cost
FROM v_product_pack_profile vpp
JOIN product_classification_unified pcu USING (product_id)
LEFT JOIN vend_products vp ON vp.id = pcu.product_id
LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
WHERE pcu.product_id = '<product_id_here>';

-------------------------------------------------------------------------------
E) OPS CHECKLISTS FOR TUNING
-------------------------------------------------------------------------------
- Too many Unknown categories. Map the top vendor categories feeding Unknown and set category_weights for those buckets.
- Freight too high. Confirm volumetric dims are present and cartons define better per unit packing.
- Damages on arrival. Set must_outer_pack to 1 and increase ship_pack_min.
- Excess tickets. Increase outer_multiple or define units_per_carton.
- Wrong container chosen. Use pick_container_explain_json to see top 3 candidates and adjust caps and costs.

-------------------------------------------------------------------------------
F) FUTURE PROOFING HOOKS
-------------------------------------------------------------------------------
- carriers.volumetric_factor default 200 to support carrier specific volumetric rules
- freight_rules.zone_code nullable to support zoned pricing later
- freight_rules.effective_from and effective_to for price changes over time
- containers.soft_deleted and freight_rules.soft_deleted for non destructive deprecations
- containers.service_id supports service specific containers such as Express Tonight

-------------------------------------------------------------------------------
G) REMAINING CONTENT WORK
-------------------------------------------------------------------------------
- Refine Unknowns by mapping high volume vendor categories to CIS categories
- Weights. Fill vend_products.avg_weight_grams for top movers. Category fallbacks work but concrete weights improve accuracy
- Carton specs. Define units_per_carton for fragile or liquid categories and bulky skus

-------------------------------------------------------------------------------
VIEWS AND DASHBOARDS TO KEEP OR DROP
-------------------------------------------------------------------------------
Freight, packaging, and categorisation core to shipping
- v_carrier_container_prices is the authoritative pricebook. Keep.
  Health sample
  SELECT carrier_name, service_name, container_code, cost FROM v_carrier_container_prices LIMIT 20;

- v_freight_rules_catalog is a sanity sheet joining carriers, containers, and rules. Keep.
  Health sample
  SELECT * FROM v_freight_rules_catalog ORDER BY carrier_name, container_code LIMIT 20;

- v_carrier_caps flags where rule cap exceeds container cap. Keep.

- v_missing_container_rules finds containers without freight rules. Keep.

- v_zero_or_null_prices flags zero or null rule prices. Keep.

- v_nzpost_eship_containers lists NZ Post eShip subset. Keep if workflows need it. Optional otherwise.

- v_packrule_coverage shows coverage of pack rules and gaps. Keep.

- v_product_pack_profile is the packaging truth per product. Keep.
  Sample
  SELECT * FROM v_product_pack_profile LIMIT 20;

- v_effective_pack_rules if it duplicates v_product_pack_profile then consolidate.

- v_category_mappings vendor to CIS category. Keep for debugging.

- v_classification_coverage mapped versus unknown counts. Keep.

- v_weight_coverage product versus category versus missing weight coverage. Keep.

- v_weight_gaps products missing both product and category weights. Keep.

- v_unknown_products products currently in Unknown category. Keep.

- freight_rules_compat likely a legacy shim using text container names. Drop if unused.

Queueing and system health
- v_cis_queue_backlog backlog per queue. Keep.
- v_cis_queue_performance throughput and latency over time. Keep.
- v_cis_system_health composite health. Keep.
- v_queue_summary per queue counts snapshot. Keep.
- cishub_jobs, cishub_jobs_dlq, cishub_job_logs lists, dead letter, and logs. Keep.
- cishub_rate_limits API rate limit headroom. Keep.
- cishub_sync_cursors last processed cursors. Keep.
- cishub_webhook_events and health and stats and subscriptions for webhook intake. Keep.
- v_master_session_status aggregates sessions or locks. Keep if unique. Consolidate if duplicate.

Purchasing, receiving, invoices, reconciliation
- cishub_purchase_orders and cishub_purchase_order_lines. Keep.
- v_receiving_dashboard inbound receipts status. Keep.
- purchase_order_items likely enrich. Keep or merge if redundant.
- invoice_processing_overview intake to match to exceptions. Keep.
- reconciliation_summary finance status. Keep.
- manual_review_required exceptions needing eyes. Keep.
- pricing_matrix if live. Keep or narrow scope if superseded by v_carrier_container_prices.

Inventory, audits, transfers, sales
- v_recent_sales_12w sales slice for velocity. Keep.
- v_transfer_eligible_products and corrected and filtered. Keep but merge overlapping.
- store_audit_* score views. Keep.
- cishub_returns and cishub_return_lines. Keep.
- cishub_stocktakes and cishub_stocktake_lines. Keep.
- auto_approval_candidates timesheets or POs etc. Keep if accurate.

What to drop or consolidate if unused
- freight_rules_compat drop once confirmed unused
- v_effective_pack_rules consolidate into v_product_pack_profile if overlapping
- purchase_order_items drop if relic and covered by cishub_purchase tables
- v_transfer_eligible_products trio reduce to final filtered version if others are supersets

Safe drop pattern with optional snapshot
CREATE TABLE zzz_backup_views LIKE some_base_table; -- optional snapshot if needed
DROP VIEW IF EXISTS view_name;

-------------------------------------------------------------------------------
QUICK METADATA HELPERS
-------------------------------------------------------------------------------
List all views with join signal
SELECT t.table_name AS view_name,
       t.create_time,
       v.view_definition LIKE '%JOIN%' AS is_join_heavy
FROM information_schema.tables t
JOIN information_schema.views v
  ON v.table_schema = t.table_schema AND v.table_name = t.table_name
WHERE t.table_schema = DATABASE() AND t.table_type = 'VIEW'
ORDER BY is_join_heavy DESC, t.table_name;

Who references a base table such as freight_rules
SELECT v.table_name AS view_name
FROM information_schema.views v
WHERE v.table_schema = DATABASE()
  AND v.view_definition LIKE '%freight_rules%';

Show DDL for a view
SHOW CREATE VIEW v_effective_pack_rules;

-------------------------------------------------------------------------------
TLDR KEEPERS
-------------------------------------------------------------------------------
Freight
- v_carrier_container_prices
- v_freight_rules_catalog
- v_carrier_caps
- v_missing_container_rules
- v_zero_or_null_prices
- v_nzpost_eship_containers optional

Packaging and category
- v_product_pack_profile
- v_packrule_coverage
- v_category_mappings
- v_classification_coverage
- v_weight_coverage
- v_weight_gaps
- v_unknown_products

Ops and health
- v_cis_queue_backlog
- v_cis_queue_performance
- v_cis_system_health
- v_queue_summary
- v_master_session_status if unique

Purchasing and receiving and finance
- v_receiving_dashboard
- invoice_processing_overview
- reconciliation_summary
- manual_review_required
- cishub_purchase_* tables

Inventory and transfers and sales
- v_recent_sales_12w
- v_transfer_eligible_products_* with consolidation
- cishub_stocktakes*
- cishub_returns*

-------------------------------------------------------------------------------
BOT PRIMER QUICK START
-------------------------------------------------------------------------------
For bots, the minimal workflow to price freight for a line item by carrier

1. Resolve packaging profile
   SELECT * FROM v_product_pack_profile WHERE product_id = :pid;

2. Compute ship units
   SELECT calc_ship_units(:qty, :pack_size, :ship_pack_min, :outer_multiple, :must_outer_pack, :units_per_carton, :carton_mandatory);

3. Compute total grams
   SELECT ship_units * COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 100)
   FROM vend_products vp
   LEFT JOIN category_weights cw ON cw.category_id = :category_id
   WHERE vp.id = :pid;

4. Pick container and get price
   SELECT pick_container_cost(:carrier_id, NULL, NULL, NULL, :total_grams);

5. If debugging, get explain
   SELECT pick_container_explain_json(:carrier_id, :L, :W, :H, :total_grams);

For bots mapping categories
- Upsert vend_category_map for unmapped vendor categories
- Refresh product_classification_unified with method and confidence
- Use v_classification_coverage to report progress

For bots maintaining pricebook
- Show v_carrier_container_prices as single source of truth
- Alarm on v_zero_or_null_prices and v_missing_container_rules
- Re-run after any container or freight_rules change

-------------------------------------------------------------------------------
HUMAN PRIMER QUICK START
-------------------------------------------------------------------------------
If you are pricing shipping for an order line
- Quantity to ship is not always the order qty. It may be rounded up to pack size, outer multiple, or full cartons.
- Weight comes from product weight or category fallback.
- Container is chosen by capacity and dimensions and cost.
- Prices are read from freight_rules and surfaced in v_carrier_container_prices.

If you are fixing Unknown categories
- Map the top vendor categories feeding Unknown, then re-run v_classification_coverage.
- Set category_weights for those categories to improve freight accuracy until product weights are filled.

If you are adding a new carrier or pack
- Insert carrier, services, containers, and freight_rules.
- Verify with v_freight_rules_catalog and v_carrier_container_prices.
- Add allowed delivery options in carrier_service_options.

-------------------------------------------------------------------------------
END OF DOCUMENT
-------------------------------------------------------------------------------
