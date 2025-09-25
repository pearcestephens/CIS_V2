-- Migration: print_jobs queue for label/document printing

CREATE TABLE IF NOT EXISTS `print_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(64) NOT NULL DEFAULT 'default',
  `job_type` VARCHAR(32) NOT NULL COMMENT 'label|document',
  `payload` JSON NOT NULL COMMENT '{"type":"label","url":"...","printer":"ZPL-1"}',
  `priority` TINYINT NOT NULL DEFAULT 5,
  `status` ENUM('queued','picked','done','error','cancelled') NOT NULL DEFAULT 'queued',
  `agent_id` VARCHAR(64) DEFAULT NULL,
  `available_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `picked_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_status_prio` (`queue`,`status`,`priority`,`available_at`),
  KEY `idx_agent_status` (`agent_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Async print jobs for local agents to poll and execute';
