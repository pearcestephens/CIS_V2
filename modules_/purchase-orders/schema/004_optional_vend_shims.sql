-- Optional shims so pages render even if Vend tables are partial
CREATE TABLE IF NOT EXISTS vend_products (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NULL,
  sku  VARCHAR(255) NULL,
  image_url VARCHAR(255) NULL,
  KEY idx_vp_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vend_inventory (
  product_id VARCHAR(64) NOT NULL,
  outlet_id  VARCHAR(64) NOT NULL,
  inventory_level INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, outlet_id),
  KEY idx_vi_outlet (outlet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vend_outlets (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NULL,
  KEY idx_vo_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vend_suppliers (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NULL,
  KEY idx_vs_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
