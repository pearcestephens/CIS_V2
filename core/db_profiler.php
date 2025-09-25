<?php
declare(strict_types=1);

/**
 * DB Profiler wrapper.
 * - Wraps a PDO-like handle and tracks query count + total time (ms).
 * - Rollup accumulates into $GLOBALS['__CIS_PROFILE']['sql_time'] / ['sql_count'].
 *
 * Usage:
 *   require_once /core/db_profiler.php
 *   $pdo = new PDO(...);
 *   $db  = new ProfiledPDO($pdo);
 *   // Provide $db() helper if you use db() globally.
 */

final class ProfiledPDO
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function prepare(string $sql, array $opts = []): PDOStatement
    {
        return $this->pdo->prepare($sql, $opts);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function exec(string $sql): int|false
    {
        $t0 = microtime(true);
        try { return $this->pdo->exec($sql); }
        finally { self::rollup($t0); }
    }

    public function query(string $sql, ?int $fetchMode = null, ...$fetchModeArgs): PDOStatement|false
    {
        $t0 = microtime(true);
        try { return $this->pdo->query($sql, $fetchMode ?? PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetchModeArgs); }
        finally { self::rollup($t0); }
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $t0 = microtime(true);
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue(is_int($k)?$k+1:(':'.$k), $v);
        $st->execute();
        self::rollup($t0);
        return $st;
    }

    public function fetch(string $sql, array $params = []): array|null
    {
        $st = $this->run($sql, $params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $st = $this->run($sql, $params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(string $table, array $assoc): void
    {
        $cols = array_keys($assoc);
        $place = array_map(fn($c)=>':'.$c, $cols);
        $sql = "INSERT INTO `$table` (`".implode('`,`',$cols)."`) VALUES (".implode(',',$place).")";
        $this->run($sql, $assoc);
    }

    private static function rollup(float $t0): void
    {
        $ms = (int)round((microtime(true) - $t0)*1000);
        $GLOBALS['__CIS_PROFILE']['sql_time']  = ($GLOBALS['__CIS_PROFILE']['sql_time']  ?? 0) + $ms;
        $GLOBALS['__CIS_PROFILE']['sql_count'] = ($GLOBALS['__CIS_PROFILE']['sql_count'] ?? 0) + 1;
    }
}
