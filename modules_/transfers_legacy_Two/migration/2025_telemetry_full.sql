-- =======================================================
-- CIS Telemetry + Profiling Tables (Unified Monitoring)
-- =======================================================

-- ---------------------------
-- DROP statements (rollback)
-- ---------------------------
DROP VIEW IF EXISTS v_user_activity_summary;
DROP TABLE IF EXISTS user_activity_log;
DROP TABLE IF EXISTS system_profiling_log;
DROP TABLE IF EXISTS devtools_events;
DROP TABLE IF EXISTS system_error_log;

-- ---------------------------
-- CREATE: User Activity Log
-- ---------------------------
CREATE TABLE IF NOT EXISTS user_activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_id VARCHAR(64) NOT NULL,
  page VARCHAR(255) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  event_data JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_session (user_id, session_id, created_at),
  KEY idx_event_type (event_type, created_at),
  KEY idx_page (page, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------
-- CREATE: System Profiling Log
-- ---------------------------
CREATE TABLE IF NOT EXISTS system_profiling_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64),
  user_id INT,
  endpoint VARCHAR(255),
  php_time_ms INT,
  sql_time_ms INT,
  sql_count INT,
  memory_mb DECIMAL(6,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_endpoint (endpoint, created_at),
  KEY idx_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------
-- CREATE: DevTools Events (optional)
-- ---------------------------
CREATE TABLE IF NOT EXISTS devtools_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_id VARCHAR(64) NOT NULL,
  page VARCHAR(255) NOT NULL,
  event_type ENUM('devtools_open','devtools_closed') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_session (user_id, session_id, created_at),
  KEY idx_event_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------
-- CREATE: System Error Log (optional)
-- ---------------------------
CREATE TABLE IF NOT EXISTS system_error_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  session_id VARCHAR(64),
  endpoint VARCHAR(255),
  error_message TEXT,
  stack_trace TEXT,
  severity ENUM('notice','warning','error','critical') DEFAULT 'error',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id, created_at),
  KEY idx_endpoint (endpoint, created_at),
  KEY idx_severity (severity, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------
-- VIEW: Rollup Summary for User Activity
-- ---------------------------
CREATE OR REPLACE VIEW v_user_activity_summary AS
SELECT
  ual.user_id,
  ual.session_id,
  MIN(ual.created_at) AS session_start,
  MAX(ual.created_at) AS session_end,
  TIMESTAMPDIFF(SECOND, MIN(ual.created_at), MAX(ual.created_at)) AS session_duration_sec,
  COUNT(*) AS total_events,
  SUM(event_type='click') AS clicks,
  SUM(event_type='mousemove') AS mouse_moves,
  SUM(event_type='keystroke') AS keystrokes,
  SUM(event_type='idle_start') AS idle_events,
  SUM(event_type='devtools_open') AS devtools_opens,
  SUM(event_type='devtools_closed') AS devtools_closes
FROM user_activity_log ual
GROUP BY ual.user_id, ual.session_id;
