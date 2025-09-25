-- Events and receipts for audit + admin tab
CREATE TABLE IF NOT EXISTS po_events (
  event_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  event_type        VARCHAR(64) NOT NULL,
  event_data        JSON NULL,
  created_by        BIGINT UNSIGNED NULL,
  created_at        DATETIME NOT NULL,
  KEY idx_po_events_po (purchase_order_id),
  KEY idx_po_events_type (event_type),
  KEY idx_po_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS po_receipts (
  receipt_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  outlet_id         VARCHAR(64) NOT NULL,
  is_final          TINYINT(1) NOT NULL DEFAULT 0,
  created_by        BIGINT UNSIGNED NULL,
  created_at        DATETIME NOT NULL,
  KEY idx_po_receipts_po (purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS po_receipt_items (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  receipt_id   BIGINT UNSIGNED NOT NULL,
  product_id   VARCHAR(64) NOT NULL,
  expected_qty INT NOT NULL DEFAULT 0,
  received_qty INT NOT NULL DEFAULT 0,
  line_note    VARCHAR(255) NULL,
  KEY idx_po_receipt_items_receipt (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS po_evidence (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  evidence_type     VARCHAR(64) NOT NULL,
  file_path         VARCHAR(255) NOT NULL,
  description       VARCHAR(255) NULL,
  uploaded_by       BIGINT UNSIGNED NULL,
  uploaded_at       DATETIME NOT NULL,
  KEY idx_po_evidence_po (purchase_order_id),
  KEY idx_po_evidence_type (evidence_type),
  KEY idx_po_evidence_created (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency cache for safe retries
CREATE TABLE IF NOT EXISTS idempotency_keys (
  idem_key      VARCHAR(190) NOT NULL PRIMARY KEY,
  request_hash  VARCHAR(64)  NOT NULL DEFAULT '',
  response_json JSON NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
