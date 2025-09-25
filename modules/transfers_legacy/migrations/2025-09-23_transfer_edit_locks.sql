-- Schema for transfer edit locks and live snapshots
CREATE TABLE IF NOT EXISTS transfer_locks (
  transfer_id        BIGINT NOT NULL PRIMARY KEY,
  owner_user_id      BIGINT NOT NULL,
  owner_name         VARCHAR(255) NOT NULL,
  acquired_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_heartbeat     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at         TIMESTAMP GENERATED ALWAYS AS (DATE_ADD(acquired_at, INTERVAL 15 MINUTE)) VIRTUAL,
  requester_user_id  BIGINT NULL,
  requester_name     VARCHAR(255) NULL,
  requested_at       TIMESTAMP NULL,
  CONSTRAINT fk_transfer_locks_owner CHECK (owner_user_id > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transfer_edit_snapshots (
  transfer_id   BIGINT NOT NULL PRIMARY KEY,
  snapshot_json LONGTEXT NOT NULL,
  updated_by    BIGINT NOT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  version       INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
