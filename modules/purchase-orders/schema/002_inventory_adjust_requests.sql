-- Queue table used by save_progress/submit_* actions
CREATE TABLE IF NOT EXISTS inventory_adjust_requests (
  request_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transfer_id    BIGINT UNSIGNED NULL,
  outlet_id      VARCHAR(64) NOT NULL,
  product_id     VARCHAR(64) NOT NULL,
  delta          INT NOT NULL,
  reason         VARCHAR(64) NOT NULL,
  source         VARCHAR(64) NOT NULL,   -- 'purchase-order' / 'po-final' / etc
  status         ENUM('pending','queued','processing','done','failed') NOT NULL DEFAULT 'pending',
  idempotency_key VARCHAR(190) NOT NULL UNIQUE,
  requested_by   BIGINT UNSIGNED NULL,
  requested_at   DATETIME NOT NULL,
  processed_at   DATETIME NULL,
  error_msg      VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
