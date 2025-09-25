<?php
/**
 * File: core/session/DbSessionHandler.php
 * Purpose: Implements database-backed PHP session handling to mirror legacy CIS behaviour.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: PDO extension, SessionHandlerInterface
 */
declare(strict_types=1);

namespace CIS\Core\Session;

use DateTimeImmutable;
use PDO;
use PDOStatement;
use SessionHandlerInterface;

final class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    /**
     * @var string
     */
    private string $table = 'Session';

    private int $ttl;

    public function __construct(PDO $pdo, ?int $ttl = null)
    {
        $this->pdo = $pdo;
        $this->ttl = $ttl ?? (int) ini_get('session.gc_maxlifetime');
    }

    /**
     * Initialise session storage lifecycle.
     *
     * @param string $savePath Session save path (unused).
     * @param string $name      Session name.
     */
    public function open($savePath, $name): bool
    {
        return true;
    }

    /**
     * Close handler hook.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Read session payload from the database when requested by PHP.
     *
     * @param string $id Session identifier in hex format.
     */
    public function read($id): string
    {
        $sql = "SELECT Session_Data
                  FROM {$this->table}
                 WHERE Session_Id = UNHEX(:id)
                   AND Session_Expires > NOW()
                 LIMIT 1";
        $statement = $this->prepare($sql);
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_NUM);

        return $row ? (string) $row[0] : '';
    }

    /**
     * Persist a session payload to the database.
     *
     * @param string $id   Session identifier in hex format.
     * @param string $data Serialized session payload.
     */
    public function write($id, $data): bool
    {
        $expiresAt = (new DateTimeImmutable("+{$this->ttl} seconds"))->format('Y-m-d H:i:s');
        $sql = "INSERT INTO {$this->table} (Session_Id, Session_Expires, Session_Data)
                VALUES (UNHEX(:id), :exp, :data)
                ON DUPLICATE KEY UPDATE
                    Session_Expires = VALUES(Session_Expires),
                    Session_Data    = VALUES(Session_Data)";
        $statement = $this->prepare($sql);

        return $statement->execute([
            ':id'   => $id,
            ':exp'  => $expiresAt,
            ':data' => $data,
        ]);
    }

    /**
     * Remove a specific session from storage.
     *
     * @param string $id Session identifier in hex format.
     */
    public function destroy($id): bool
    {
        $statement = $this->prepare("DELETE FROM {$this->table} WHERE Session_Id = UNHEX(:id)");

        return $statement->execute([':id' => $id]);
    }

    /**
     * Garbage-collect expired sessions.
     *
     * @param int $max_lifetime Maximum lifetime seconds (unused).
     */
    public function gc($max_lifetime): int|false
    {
        $statement = $this->prepare("DELETE FROM {$this->table} WHERE Session_Expires <= NOW()");
        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * Prepare a SQL statement against the configured PDO connection.
     */
    private function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }
}
