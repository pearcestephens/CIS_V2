<?php
declare(strict_types=1);
/**
 * MODULE_SCAFFOLDER — Web-based builder for new modules
 * URL: https://staff.vapeshed.co.nz/modules/MODULE_SCAFFOLDER.php
 * Requires: Logged-in user with admin privileges (basic check here; tighten as needed)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
http_response_code(410);
echo '<div class="container my-3"><div class="alert alert-warning">This scaffolder has moved. Please use <a href="https://staff.vapeshed.co.nz/admin-ui/module-create/">https://staff.vapeshed.co.nz/admin-ui/module-create/</a>.</div></div>';

function ms_is_admin(): bool {
    $uid = (int)($_SESSION['userID'] ?? 0);
    return $uid === 1 || $uid === 42 || (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']);
}

if (!ms_is_admin()) {
    http_response_code(403);
    echo '<div class="container my-3"><div class="alert alert-danger">Forbidden: Admins only.</div></div>';
    exit;
}

$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf;

function ms_clean_slug(string $s): string { return strtolower(preg_replace('/[^a-z0-9-]+/', '-', trim($s))) ?: 'new-module'; }

// Insert action mappings into template handler's $map
function ms_insert_handler_mappings(string $handlerPath, array $entries): void {
  if (!file_exists($handlerPath)) return;
  $code = file_get_contents($handlerPath);
  $open = strpos($code, '$map = [');
  if ($open === false) return;
  $close = strpos($code, "];", $open);
  if ($close === false) return;
  $before = substr($code, 0, $close);
  $after  = substr($code, $close);
  $inserts = "\n    " . implode(",\n    ", $entries) . ",\n";
  file_put_contents($handlerPath, $before . $inserts . $after);
}

// Add admin guard function if missing in tools.php
function ms_add_admin_guard_func(string $toolsPath): void {
  if (!file_exists($toolsPath)) return;
  $code = file_get_contents($toolsPath);
  if (strpos($code, 'function mt_require_admin(') !== false) return;
  $guard = <<<'PHP'

/** Admin guard: userID 1/42 or session is_admin */
function mt_require_admin(): void {
  $uid = (int)($_SESSION['userID'] ?? 0);
  $is = !empty($_SESSION['is_admin']);
  if (!($uid === 1 || $uid === 42 || $is)) {
    mt_json(false, ['code'=>'FORBIDDEN','message'=>'Admin required']);
    exit;
  }
}
PHP;
  file_put_contents($toolsPath, $code . $guard);
}

// Insert admin guard call into handler (after CSRF verify)
function ms_add_admin_guard_call(string $handlerPath): void {
  if (!file_exists($handlerPath)) return;
  $code = file_get_contents($handlerPath);
  if (strpos($code, 'mt_require_admin(') !== false) return;
  $code = str_replace("mt_verify_csrf();", "mt_verify_csrf();\n\n// Admin-only module\nmt_require_admin();", $code);
  file_put_contents($handlerPath, $code);
}

// Parse column spec into [name, definition]
function ms_parse_columns(string $spec): array {
  $cols = [];
  foreach (preg_split('/\n|,/', $spec) as $raw) {
    $raw = trim($raw);
    if ($raw === '') continue;
    $parts = explode(':', $raw, 2);
    $name = trim($parts[0]);
    $def  = trim($parts[1] ?? 'varchar(255)');
    if ($name) $cols[] = [$name, $def];
  }
  return $cols;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        echo 'Invalid CSRF token'; exit;
    }
  $slug = ms_clean_slug($_POST['module_slug'] ?? '');
  $name = trim($_POST['module_name'] ?? 'New Module');
  $desc = trim($_POST['module_desc'] ?? '');

  $withAdmin  = isset($_POST['with_admin']);
  $withIndex  = isset($_POST['with_index']);
  $withSchema = isset($_POST['with_schema']);
  $withEvents = isset($_POST['with_events']);
  $withCrud   = isset($_POST['with_crud']);
  $adminOnly  = isset($_POST['admin_only']);

  $tableName  = ms_clean_slug($_POST['base_table'] ?? ($slug.'-items'));
  $tableName  = str_replace('-', '_', $tableName);
  $entityName = trim($_POST['crud_entity'] ?? 'Item');
  $colSpec    = trim($_POST['base_columns'] ?? 'name:varchar(255), status:tinyint unsigned default 0');
  $columns    = ms_parse_columns($colSpec);
    $dest = __DIR__ . '/' . $slug;
    $src  = __DIR__ . '/MODULE_TEMPLATE';
    if (!is_dir($src)) { echo 'Template missing'; exit; }
    if (is_dir($dest)) { echo 'Destination already exists'; exit; }
    // Copy template recursively
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $file) {
        $target = $dest . substr((string)$file, strlen($src));
        if ($file->isDir()) {
            @mkdir($target, 0775, true);
        } else {
            $content = file_get_contents((string)$file);
      $content = str_replace(['__MODULE_SLUG__','__MODULE_NAME__'], [$slug,$name], $content);
      if (substr($target, -9) === 'README.md' && $desc) {
        $content .= "\nDescription\n-----------\n" . $desc . "\n";
      }
            file_put_contents($target, $content);
        }
    }
    // Optional trims
    if (!$withAdmin) {
        @unlink($dest . '/views/admin/dashboard.php');
        @rmdir($dest . '/views/admin');
    }
    if (!$withIndex) {
        @unlink($dest . '/views/index.php');
    }
  // Schema: extend 001_core.sql with base table and optional events removal
  if ($withSchema) {
    $schemaPath = $dest . '/schema/001_core.sql';
    if (file_exists($schemaPath)) {
      $sql = file_get_contents($schemaPath);
      $sql .= "\n-- Base table for CRUD\n";
      $sql .= "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
      $sql .= "  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n";
      foreach ($columns as [$n,$d]) {
        $n = preg_replace('/[^a-z0-9_]/','_', strtolower($n));
        $sql .= "  `{$n}` {$d},\n";
      }
      $sql .= "  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
      $sql .= "  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n";
      $sql .= "  PRIMARY KEY (id)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
      if (!$withEvents) {
        $sql = preg_replace('/CREATE TABLE IF NOT EXISTS .*?_events[\s\S]*?;\n/i', '', $sql);
      }
      file_put_contents($schemaPath, $sql);
    }
  }

  // CRUD stubs and handler wiring
  if ($withCrud) {
    $actionsDir = $dest . '/ajax/actions';
    @mkdir($actionsDir, 0775, true);
    $list = "<?php\ndeclare(strict_types=1);\nfunction mt_list(): array {\n    \$pdo = mt_pdo();\n    \$stmt = \$pdo->query('SELECT * FROM `{$tableName}` ORDER BY id DESC LIMIT 100');\n    return ['items' => \$stmt->fetchAll()];\n}\n";
    $create = "<?php\ndeclare(strict_types=1);\nfunction mt_create(): array {\n    \$pdo = mt_pdo();\n    \$name = trim((string)(\$_POST['name'] ?? ''));\n    if (\$name === '') throw new RuntimeException('name required');\n    \$stmt = \$pdo->prepare('INSERT INTO `{$tableName}` (`name`) VALUES (?)');\n    \$stmt->execute([\$name]);\n    return ['id' => (int)\$pdo->lastInsertId()];\n}\n";
    $update = "<?php\ndeclare(strict_types=1);\nfunction mt_update(): array {\n    \$pdo = mt_pdo();\n    \$id = (int)(\$_POST['id'] ?? 0);\n    \$name = trim((string)(\$_POST['name'] ?? ''));\n    if (\$id <= 0) throw new RuntimeException('id required');\n    \$stmt = \$pdo->prepare('UPDATE `{$tableName}` SET `name`=? WHERE id=?');\n    \$stmt->execute([\$name, \$id]);\n    return ['updated' => \$stmt->rowCount()];\n}\n";
    $delete = "<?php\ndeclare(strict_types=1);\nfunction mt_delete(): array {\n    \$pdo = mt_pdo();\n    \$id = (int)(\$_POST['id'] ?? 0);\n    if (\$id <= 0) throw new RuntimeException('id required');\n    \$pdo->prepare('DELETE FROM `{$tableName}` WHERE id=?')->execute([\$id]);\n    return ['deleted' => true];\n}\n";
    file_put_contents($actionsDir . '/list.php', $list);
    file_put_contents($actionsDir . '/create.php', $create);
    file_put_contents($actionsDir . '/update.php', $update);
    file_put_contents($actionsDir . '/delete.php', $delete);

    $handler = $dest . '/ajax/handler.php';
    $entries = [
      "'{$slug}.list' => ['list.php','mt_list']",
      "'{$slug}.create' => ['create.php','mt_create']",
      "'{$slug}.update' => ['update.php','mt_update']",
      "'{$slug}.delete' => ['delete.php','mt_delete']",
    ];
    ms_insert_handler_mappings($handler, $entries);
  }

  // Admin-only protection
  if ($adminOnly) {
    ms_add_admin_guard_func($dest . '/ajax/tools.php');
    ms_add_admin_guard_call($dest . '/ajax/handler.php');
  }

  echo '<div class="container my-3"><div class="alert alert-success">Module created at /modules/'.htmlspecialchars($slug).'/. Run <a href="https://staff.vapeshed.co.nz/modules/'.htmlspecialchars($slug).'/schema/migrate.php">migrator</a>.</div>'
    . '<div class="card"><div class="card-header"><strong>Summary</strong></div><div class="card-body">'
    . '<ul>'
    . '<li>Name: '.htmlspecialchars($name).'</li>'
    . '<li>Slug: '.htmlspecialchars($slug).'</li>'
    . '<li>Views: '.($withIndex?'index ':'').($withAdmin?'+ admin ':'(none)').'</li>'
    . '<li>Schema: '.($withSchema?'Yes':'No').($withSchema?' (table `'.htmlspecialchars($tableName).'`)':'').'</li>'
    . '<li>Events: '.($withEvents?'Yes':'No').'</li>'
    . '<li>CRUD: '.($withCrud?'Yes':'No').'</li>'
    . '<li>Admin-only: '.($adminOnly?'Yes':'No').'</li>'
    . '</ul></div></div></div>';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Module Scaffolder</title>
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/modules/MODULE_TEMPLATE/assets/css/module.css">
  <style> .ms-wrap{max-width:980px;margin:24px auto} .form-help{color:#6c757d;font-size:12px} .card + .card{margin-top:16px} </style>
  <meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
  <script>
    function suggestSlug(){
      const name = document.getElementById('module_name').value.trim();
      if(!name) return; 
      let slug = name.toLowerCase().replace(/[^a-z0-9-]+/g,'-').replace(/^-+|-+$/g,'');
      document.getElementById('module_slug').value = slug || 'new-module';
    }
  </script>
  <style> label{font-weight:600} </style>
  </head>
<body>
  <div class="container ms-wrap">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="mb-0">Module Scaffolder</h3>
        <a class="btn btn-sm btn-outline-secondary" href="https://staff.vapeshed.co.nz/modules/_shared/diagnostics.php">Diagnostics</a>
      </div>
      <form method="post" class="card-body">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="module_name">Module Name</label>
              <input class="form-control" id="module_name" name="module_name" placeholder="e.g., Purchase Forecasts" onblur="suggestSlug()" required>
              <div class="form-help">Human-friendly title used in views and docs.</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="module_slug">Module Slug</label>
              <input class="form-control" id="module_slug" name="module_slug" placeholder="e.g., purchase-forecasts" required>
              <div class="form-help">Folder name under /modules/. Lowercase letters, numbers, and dashes only.</div>
            </div>
          </div>
        </div>
        <div class="form-group mt-2">
          <label for="module_desc">Description</label>
          <textarea class="form-control" id="module_desc" name="module_desc" rows="2" placeholder="Optional short description"></textarea>
        </div>

        <div class="row mt-3">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header"><strong>Views</strong></div>
              <div class="card-body">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="with_index" name="with_index" checked>
                  <label class="form-check-label" for="with_index">Include user index view</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="with_admin" name="with_admin" checked>
                  <label class="form-check-label" for="with_admin">Include admin dashboard view</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="admin_only" name="admin_only">
                  <label class="form-check-label" for="admin_only">Require admin access for AJAX handler</label>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header"><strong>Schema</strong></div>
              <div class="card-body">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="with_schema" name="with_schema" checked>
                  <label class="form-check-label" for="with_schema">Include base schema (001_core.sql)</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="with_events" name="with_events" checked>
                  <label class="form-check-label" for="with_events">Include events table</label>
                </div>
                <div class="form-group mt-2">
                  <label for="base_table">Base table name</label>
                  <input class="form-control" id="base_table" name="base_table" placeholder="e.g., my_module_items">
                </div>
                <div class="form-group mt-2">
                  <label for="base_columns">Columns (CSV or newline, name:type)</label>
                  <textarea class="form-control" id="base_columns" name="base_columns" rows="3">name:varchar(255), status:tinyint unsigned default 0</textarea>
                  <div class="form-help">Example: price:decimal(10,2) unsigned default 0, enabled:tinyint unsigned default 1</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header"><strong>CRUD Actions</strong></div>
          <div class="card-body">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="with_crud" name="with_crud" checked>
              <label class="form-check-label" for="with_crud">Generate list/create/update/delete action stubs</label>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <label for="crud_entity">CRUD Entity Name</label>
                <input class="form-control" id="crud_entity" name="crud_entity" placeholder="e.g., Item">
              </div>
            </div>
            <div class="form-help mt-1">Actions created: <code>slug.list</code>, <code>slug.create</code>, <code>slug.update</code>, <code>slug.delete</code></div>
          </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Create Module</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>