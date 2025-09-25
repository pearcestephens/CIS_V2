-- Migration: Ensure helpful indexes exist on logs/audit tables (portable)
-- Uses dynamic SQL to avoid failure when index already exists (works on MySQL/MariaDB).

-- helper: create index if missing
SET @db := DATABASE();

-- transfer_logs.idx_logs_transfer_created
SET @exists := (
	SELECT COUNT(*) FROM information_schema.statistics
	WHERE table_schema=@db AND table_name='transfer_logs' AND index_name='idx_logs_transfer_created'
);
SET @sql := IF(@exists=0,
	'CREATE INDEX `idx_logs_transfer_created` ON `transfer_logs` (`transfer_id`,`created_at`)',
	'SELECT 1'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- transfer_logs.idx_logs_event_created
SET @exists := (
	SELECT COUNT(*) FROM information_schema.statistics
	WHERE table_schema=@db AND table_name='transfer_logs' AND index_name='idx_logs_event_created'
);
SET @sql := IF(@exists=0,
	'CREATE INDEX `idx_logs_event_created` ON `transfer_logs` (`event_type`,`created_at`)',
	'SELECT 1'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- transfer_audit_log.idx_audit_transfer_created
SET @exists := (
	SELECT COUNT(*) FROM information_schema.statistics
	WHERE table_schema=@db AND table_name='transfer_audit_log' AND index_name='idx_audit_transfer_created'
);
SET @sql := IF(@exists=0,
	'CREATE INDEX `idx_audit_transfer_created` ON `transfer_audit_log` (`transfer_id`,`created_at`)',
	'SELECT 1'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- transfer_audit_log.idx_audit_action_created
SET @exists := (
	SELECT COUNT(*) FROM information_schema.statistics
	WHERE table_schema=@db AND table_name='transfer_audit_log' AND index_name='idx_audit_action_created'
);
SET @sql := IF(@exists=0,
	'CREATE INDEX `idx_audit_action_created` ON `transfer_audit_log` (`action`,`created_at`)',
	'SELECT 1'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
