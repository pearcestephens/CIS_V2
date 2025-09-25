<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "Modules Diagnostics\n";
echo "- DOCUMENT_ROOT: ".($_SERVER['DOCUMENT_ROOT'] ?? '(none)')."\n";
echo "- DB constants: ".(defined('DB_HOST')?'YES':'NO')."\n";
try {
    $pdo = cis_pdo();
    $r = $pdo->query('SELECT 1')->fetchColumn();
    echo "- PDO: OK (SELECT 1 => $r)\n";
} catch (Throwable $e) {
    echo "- PDO: FAIL (".$e->getMessage().")\n";
}
$m = cis_mysqli();
if ($m instanceof mysqli) {
    $q = $m->query('SELECT 1');
    echo "- MySQLi: ".($q?"OK":"FAIL: ".$m->error)."\n";
} else {
    echo "- MySQLi: Not available\n";
}
echo "Done.\n";