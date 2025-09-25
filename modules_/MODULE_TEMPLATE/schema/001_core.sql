-- 001_core.sql — starter schema for __MODULE_NAME__
CREATE TABLE IF NOT EXISTS __MODULE_SLUG___events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(64) NOT NULL,
  payload JSON NULL,
  PRIMARY KEY (id),
  KEY idx_type_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
