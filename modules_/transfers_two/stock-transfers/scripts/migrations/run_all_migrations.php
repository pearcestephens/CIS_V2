<?php
/**
 * run_all_migrations.php
 * Scans modules/**/schema/*.sql and runs them in lexical order.
 * Usage (CLI): php run_all_migrations.php --dsn="mysql:host=127.0.0.1;dbname=cis" --user="root" --pass="secret"
 */
// PHP 5.6+ compatible (no strict_types, no null coalescing)
$options = getopt('', array('dsn:', 'user:', 'pass:', 'dir::', 'modules-dir::', 'dry-run::'));
$dsn  = isset($options['dsn']) ? $options['dsn'] : (getenv('CIS_DSN') ? getenv('CIS_DSN') : '');
$user = isset($options['user']) ? $options['user'] : (getenv('CIS_DB_USER') ? getenv('CIS_DB_USER') : '');
$pass = isset($options['pass']) ? $options['pass'] : (getenv('CIS_DB_PASS') ? getenv('CIS_DB_PASS') : '');
$root = isset($options['dir']) ? $options['dir'] : (isset($options['modules-dir']) ? $options['modules-dir'] : realpath(__DIR__ . '/../../../../'));

if (in_array('--help', $argv, true)) {
  echo "Usage: php run_all_migrations.php --dsn=DSN --user=USER --pass=PASS [--modules-dir=/path/to/modules] [--dry-run=1]\n";
  exit(0);
}

if (!$dsn) {
  fwrite(STDERR, "Missing DSN. Provide --dsn or set CIS_DSN env.\n");
  exit(2);
}

$schemaDirs = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iter as $file) {
  if ($file->isDir()) continue;
  $dir = dirname($file->getPathname());
  // Skip template schemas
  if (strpos($dir, 'MODULE_TEMPLATE') !== false) continue;
  if (substr($dir, -7) === DIRECTORY_SEPARATOR.'schema' || preg_match('~(^|/)schema$~', $dir)) {
    $schemaDirs[$dir] = true;
  }
}
$schemaDirs = array_keys($schemaDirs);
sort($schemaDirs);

$files = [];
foreach ($schemaDirs as $dir) {
  foreach (glob($dir.'/*.sql') as $sql) { $files[] = $sql; }
}
sort($files);

printf("Scanning modules at: %s\n", $root);
printf("Found %d SQL files across %d schema dirs\n", count($files), count($schemaDirs));

if (!empty($options['dry-run'])) {
  foreach ($files as $f) echo $f, "\n";
  exit(0);
}

try {
  $pdo = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC));
} catch (Exception $e) {
  fwrite(STDERR, "DB connection failed: ".$e->getMessage()."\n");
  exit(3);
}

foreach ($files as $sqlFile) {
  $sql = file_get_contents($sqlFile);
  echo "\n>>> Running: $sqlFile\n";
  try {
    $pdo->beginTransaction();
    // Split by semicolon at end of line to reduce accidental splits inside functions/comments
    $stmts = preg_split('/;\s*\n/', $sql);
    foreach ($stmts as $stmt) {
      $stmt = trim($stmt);
      if ($stmt === '' || strtoupper($stmt) === 'START TRANSACTION' || strtoupper($stmt) === 'COMMIT') continue;
      $pdo->exec($stmt);
    }
    $pdo->commit();
    echo "OK\n";
  } catch (Exception $e) {
    $pdo->rollBack();
    echo "FAILED: ".$e->getMessage()."\n";
    exit(4);
  }
}

echo "\nAll migrations completed successfully.\n";
