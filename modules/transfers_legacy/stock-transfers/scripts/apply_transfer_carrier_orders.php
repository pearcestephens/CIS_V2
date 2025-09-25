<?php
/**
 * modules/transfers/stock-transfers/scripts/apply_transfer_carrier_orders.php
 * Purpose: Apply the 004_transfer_carrier_orders.sql migration safely (idempotent).
 * Usage (CLI only):
 *   php /home/master/applications/jcepnzzkmj/public_html/modules/transfers/stock-transfers/scripts/apply_transfer_carrier_orders.php
 *
 * Dependencies:
 *   - Requires app bootstrap for DB constants (DB_HOST, DB_NAME, DB_USER, DB_PASS)
 */

declare(strict_types=1);

// Enforce CLI usage
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden"; exit(1);
}

// Bootstrap application config (CLI-safe): resolve public_html/app.php
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if (!is_string($docroot) || $docroot === '' || !is_file($docroot . '/app.php')) {
    // Try to derive docroot from script location: .../public_html/modules/transfers/stock-transfers/scripts
    $candidates = [
        dirname(__DIR__, 4), // .../public_html
        dirname(__DIR__, 5), // one level higher just in case
    ];
    foreach ($candidates as $cand) {
        if ($cand && is_dir($cand) && is_file($cand . '/app.php')) { $docroot = $cand; break; }
    }
    if (!$docroot || !is_file($docroot . '/app.php')) {
        fwrite(STDERR, "Unable to locate app.php. Tried DOCUMENT_ROOT and parent paths.\n");
        fwrite(STDERR, "Script dir: " . __DIR__ . "\n");
        exit(2);
    }
    // Set for consistency with project includes
    $_SERVER['DOCUMENT_ROOT'] = $docroot;
}
require_once $docroot . '/app.php';

// Simple PDO factory
function _pdo(): PDO {
    $host = defined('DB_HOST') ? (string)DB_HOST : (string)getenv('DB_HOST');
    $name = defined('DB_NAME') ? (string)DB_NAME : (string)getenv('DB_NAME');
    $user = defined('DB_USER') ? (string)DB_USER : (string)getenv('DB_USER');
    $pass = defined('DB_PASS') ? (string)DB_PASS : (string)getenv('DB_PASS');
    $port = defined('DB_PORT') ? (string)DB_PORT : ((string)getenv('DB_PORT') ?: '3306');

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Database configuration missing. Provide DB_HOST, DB_NAME, DB_USER (and DB_PASS) via app.php or environment.');
    }

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function tableExists(PDO $pdo, string $name): bool {
    $q = $pdo->quote($name);
    $s = $pdo->query("SHOW TABLES LIKE $q");
    return $s && $s->fetchColumn() !== false;
}

try {
    $pdo = _pdo();
    $schemaFile = __DIR__ . '/../schema/004_transfer_carrier_orders.sql';
    if (!is_file($schemaFile)) {
        fwrite(STDERR, "Schema file not found: {$schemaFile}\n");
        exit(2);
    }

    $before = tableExists($pdo, 'transfer_carrier_orders');
    $sql = file_get_contents($schemaFile);
    if ($sql === false) { throw new RuntimeException('Unable to read schema file'); }
    // Strip line comments starting with -- and block comments /* ... */ to avoid mysql parsing issues
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql; // remove block comments
    $lines = array_map(function($l){
        // remove line comments beginning with -- followed by space
        if (preg_match('/^\s*-- /', $l)) { return ''; }
        return $l;
    }, preg_split("/\r?\n/", $sql));
    $sql = trim(implode("\n", $lines));
    // Remove potential UTF-8 BOM
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    // Relax JSON CHECK constraint for broader MariaDB compatibility
    $sql = preg_replace('/,\s*`payload`\s+longtext[^,]*CHECK\s*\(\s*json_valid\s*\(`payload`\)\s*\)/i', ', `payload` longtext', $sql) ?? $sql;

    // Execute each statement individually (in case future files contain multiple)
    $stmts = array_filter(array_map('trim', preg_split('/;\s*\n|;\s*$/m', $sql)));
    $i = 0;
    foreach ($stmts as $stmt) {
        $i++;
        $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 120);
        fwrite(STDERR, "[apply-migration] Executing stmt #{$i}: {$preview}...\n");
        if ($stmt === '') { continue; }
        $pdo->exec($stmt);
    }
    $after = tableExists($pdo, 'transfer_carrier_orders');

    if ($after && !$before) {
        echo "Created table transfer_carrier_orders.\n";
    } elseif ($after && $before) {
        echo "Table transfer_carrier_orders already existed; migration is idempotent.\n";
    } else {
        echo "No changes detected. Verify permissions.\n";
    }

    // Quick describe
    $cols = $pdo->query('SHOW COLUMNS FROM transfer_carrier_orders')->fetchAll();
    echo "Columns:\n";
    foreach ($cols as $c) {
        echo " - {$c['Field']} ({$c['Type']})\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
