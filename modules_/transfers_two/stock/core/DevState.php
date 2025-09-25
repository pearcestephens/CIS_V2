<?php
declare(strict_types=1);
/**
 * modules/transfers/stock/core/DevState.php
 * Purpose: Helper to read DEV testing state file for dashboard lists.
 * Note: Safe in production – returns empty if file not present.
 */

final class DevState
{
    private static function stateFile(): string
    {
        $base = __DIR__ . '/../testing/.state.json';
        $rp = realpath(dirname($base));
        return ($rp !== false ? $rp : dirname($base)) . '/.state.json';
    }

    public static function loadAll(): array
    {
        $file = self::stateFile();
        if (!is_file($file)) { return []; }
        $json = @file_get_contents($file);
        if (!$json) { return []; }
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }

    public static function saveAll(array $state): bool
    {
        $file = self::stateFile();
        $dir = dirname($file);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $tmp = $file . '.tmp';
        $json = json_encode($state, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        if ($json === false) { return false; }
        $fh = @fopen($tmp, 'wb'); if (!$fh) { return false; }
        @flock($fh, LOCK_EX);
        $ok = @fwrite($fh, $json) !== false;
        @flock($fh, LOCK_UN); @fclose($fh);
        if (!$ok) { @unlink($tmp); return false; }
        return @rename($tmp, $file);
    }

    public static function saveOne(int $transferId, array $row): bool
    {
        $all = self::loadAll();
        $all[$transferId] = $row;
        return self::saveAll($all);
    }

    /**
     * Delete a transfer from DevState (no-op if not present)
     */
    public static function deleteOne(int $transferId): bool
    {
        $all = self::loadAll();
        if (isset($all[$transferId])) {
            unset($all[$transferId]);
            return self::saveAll($all);
        }
        return true;
    }
}
