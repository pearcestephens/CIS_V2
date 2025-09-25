-- Purchase Orders: minimal core tables (idempotent)
CREATE TABLE IF NOT EXISTS purchase_orders (
  purchase_order_id BIGINT UNSIGNED PRIMARY KEY,
  supplier_id       VARCHAR(64) NULL,
  supplier_name_cache VARCHAR(255) NULL,
  outlet_id         VARCHAR(64) NULL,
  status            TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0=open,1=completed,2=partial
  partial_delivery  TINYINT(1) NOT NULL DEFAULT 0,
  completed_timestamp DATETIME NULL,
  updated_at        DATETIME NULL,
  created_at        DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_line_items (
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  product_id        VARCHAR(64) NOT NULL,
  order_qty         INT NOT NULL DEFAULT 0,
  qty_arrived       INT NULL,
  qty_received      INT NULL,
  received_at       DATETIME NULL,
  PRIMARY KEY (purchase_order_id, product_id),
  KEY idx_po_li_po (purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
