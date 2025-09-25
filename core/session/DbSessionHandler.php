<?php
declare(strict_types=1);

namespace CISV2\Session;

use PDO;
use SessionHandlerInterface;

final class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private string $table;
    private int $ttl;

    public function __construct(PDO $pdo, array $opts = [])
    {
        $this->pdo   = $pdo;
        $this->table = $opts['table'] ?? 'Session';
        $this->ttl   = (int)($opts['gc_maxlifetime'] ?? 86400);
    }

    public function open($savePath, $name): bool { return true; }

    public function close(): bool { return true; }

    public function read($id): string|false
    {
        try {
            // Session_Expires check prevents resurrecting expired sessions
            $stmt = $this->pdo->prepare(
                "SELECT Session_Data FROM `{$this->table}`
                 WHERE Session_Id = :id AND Session_Expires > NOW() LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string)$row['Session_Data'] : '';
        } catch (\Throwable $e) {
            error_log('Session read error: '.$e->getMessage());
            return '';
        }
    }

    public function write($id, $data): bool
    {
        try {
            $expires = (new \DateTimeImmutable("+{$this->ttl} seconds"))->format('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare(
                "INSERT INTO `{$this->table}` (Session_Id, Session_Expires, Session_Data)
                 VALUES (:id, :exp, :dat)
                 ON DUPLICATE KEY UPDATE Session_Expires = VALUES(Session_Expires),
                                         Session_Data   = VALUES(Session_Data)"
            );
            return $stmt->execute([':id'=>$id, ':exp'=>$expires, ':dat'=>$data]);
        } catch (\Throwable $e) {
            error_log('Session write error: '.$e->getMessage());
            return false;
        }
    }

    public function destroy($id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE Session_Id = :id");
            $stmt->execute([':id' => $id]);
            // Clear cookie for current client
            if (PHP_SAPI !== 'cli' && isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', [
                    'expires'  => time() - 3600,
                    'path'     => ini_get('session.cookie_path') ?: '/',
                    'domain'   => ini_get('session.cookie_domain') ?: '',
                    'secure'   => (bool)ini_get('session.cookie_secure'),
                    'httponly' => (bool)ini_get('session.cookie_httponly'),
                    'samesite' => 'Lax',
                ]);
            }
            return true;
        } catch (\Throwable $e) {
            error_log('Session destroy error: '.$e->getMessage());
            return false;
        }
    }

    public function gc($max_lifetime): int|false
    {
        try {
            return $this->pdo->exec("DELETE FROM `{$this->table}` WHERE Session_Expires <= NOW()") ?: 0;
        } catch (\Throwable $e) {
            error_log('Session GC error: '.$e->getMessage());
            return false;
        }
    }
}
