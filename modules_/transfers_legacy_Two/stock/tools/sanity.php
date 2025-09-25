<?php
declare(strict_types=1);

/**
 * Transfers DB sanity (read-only).
 * URL:
 *   /modules/transfers/stock/tools/sanity.php
 *   /modules/transfers/stock/tools/sanity.php?transfer_id=12345
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$pdo = db();
$err = null;
$has = static function(string $table) use ($pdo): bool {
  try {
    $q = $pdo->prepare('SHOW TABLES LIKE ?'); $q->execute([$table]); return (bool)$q->fetchColumn();
  } catch (Throwable $e) { return false; }
};

$need = [
  'transfers', 'transfer_items', 'transfer_shipments',
  'transfer_parcels', 'transfer_parcel_items',
  'transfer_notes', 'transfer_logs', 'transfer_audit_log'
];

$schema = [];
foreach ($need as $t) { $schema[$t] = $has($t); }

$tid = isset($_GET['transfer_id']) ? (int)$_GET['transfer_id'] : 0;

$withItems = [];
try {
  $withItems = $pdo->query("
    SELECT t.id AS transfer_id,
           UPPER(t.type) AS type,
           t.status,
           (SELECT COUNT(*) FROM transfer_items ti WHERE ti.transfer_id = t.id) AS item_count,
           t.created_at
    FROM transfers t
    ORDER BY t.id DESC
    LIMIT 10
  ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $err = $e->getMessage();
}

$selected = null;
if ($tid > 0) {
  try {
    $stmt = $pdo->prepare("
      SELECT t.id AS transfer_id,
             UPPER(t.type) AS type,
             t.status,
             (SELECT COUNT(*) FROM transfer_items ti WHERE ti.transfer_id = t.id) AS item_count
      FROM transfers t
      WHERE t.id = :id
      LIMIT 1
    ");
    $stmt->execute([':id' => $tid]);
    $selected = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Transfers — Sanity</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:16px;color:#222}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .card{border:1px solid #ddd;border-radius:8px;padding:12px;background:#fff}
  .ok{color:#0a0;font-weight:600}.bad{color:#a00;font-weight:600}
  table{width:100%;border-collapse:collapse}.kv{font-family:ui-monospace,monospace}
  th,td{padding:6px;border-bottom:1px solid #eee;text-align:left;font-size:14px}
  .muted{color:#777;font-size:12px}
  .btn{display:inline-block;padding:6px 10px;border:1px solid #1971c2;color:#1971c2;border-radius:6px;text-decoration:none}
  .btn:hover{background:#e7f1ff}
</style>

<h2>Transfers — Sanity</h2>
<p class="muted">Use this to pick a real transfer that has items, then open Pack.</p>

<div class="grid">
  <div class="card">
    <h3>Schema</h3>
    <ul>
      <?php foreach ($schema as $t => $ok): ?>
        <li><?=h($t)?>: <span class="<?= $ok?'ok':'bad' ?>"><?= $ok?'present':'missing' ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($err): ?><div class="bad">Error: <?=h($err)?></div><?php endif; ?>
  </div>

  <div class="card">
    <h3>Check a transfer</h3>
    <form method="get" action="">
      <label>transfer_id</label>
      <input type="number" name="transfer_id" value="<?= (int)$tid ?>" />
      <button class="btn" type="submit">Inspect</button>
    </form>
    <?php if ($selected): ?>
      <p class="kv">#<?= (int)$selected['transfer_id'] ?> — type <?=h($selected['type']??'')?> — status <?=h($selected['status']??'')?> — items <strong><?= (int)$selected['item_count'] ?></strong></p>
      <?php if ((int)$selected['item_count'] > 0): ?>
        <p><a class="btn" target="_blank" href="/modules/transfers/stock/views/pack.php?transfer_id=<?= (int)$selected['transfer_id'] ?>">Open Pack</a></p>
      <?php else: ?>
        <p class="bad">This transfer has 0 items. Pick another below.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h3>Recent transfers (top 10)</h3>
  <div class="muted">Click to open sanity for one.</div>
  <div style="overflow:auto;max-height:50vh">
    <table>
      <thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Items</th><th>Created</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($withItems as $r): ?>
          <tr>
            <td class="kv"><?= (int)$r['transfer_id'] ?></td>
            <td><?= h($r['type']??'') ?></td>
            <td><?= h($r['status']??'') ?></td>
            <td class="kv"><?= (int)$r['item_count'] ?></td>
            <td class="kv"><?= h((string)$r['created_at']) ?></td>
            <td><a class="btn" href="?transfer_id=<?= (int)$r['transfer_id'] ?>">Check</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$withItems): ?>
          <tr><td colspan="6" class="muted">No rows returned.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
