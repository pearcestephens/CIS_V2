<?php
declare(strict_types=1);
/**
 * File: modules/transfers/stock/views/pack.php
 * Purpose: Stock Transfer Pack — Final Form view. Renders within CIS template.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/lib/view_helpers.php';

// Resolve transfer id from common query params
$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$transferIdParam = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $transferIdParam = (int)$_GET[$__k]; break; } }
if ($transferIdParam <= 0) { echo '<div class="alert alert-danger">Missing transfer ID.</div>'; return; }

// Ensure CSRF token exists for AJAX
if (!isset($_SESSION)) { session_start(); }
$csrfToken = '';
if (function_exists('getCSRFToken')) { try { $csrfToken = (string)getCSRFToken(); } catch (Throwable $e) { $csrfToken = ''; } }
if ($csrfToken === '') { $csrfToken = (string)($_SESSION['csrf_token'] ?? ''); }
if ($csrfToken === '') { $csrfToken = bin2hex(random_bytes(16)); $_SESSION['csrf_token'] = $csrfToken; }

// Base/shared styles like dashboard (keep absolute https policy via mod_asset_url)
mod_style('/modules/_shared/assets/css/cis-tokens.css?v=' . mod_v('/modules/_shared/assets/css/cis-tokens.css'));
mod_style('/modules/_shared/assets/css/cis-utilities.css?v=' . mod_v('/modules/_shared/assets/css/cis-utilities.css'));
mod_style('/modules/transfers/stock/assets/css/stx-ui.css?v=' . mod_v('/modules/transfers/stock/assets/css/stx-ui.css'));

// Pack-specific assets
mod_style('/modules/transfers/stock/assets/css/stock.css?v=' . mod_v('/modules/transfers/stock/assets/css/stock.css'));
mod_script('/modules/transfers/stock/assets/js/core.js?v=' . mod_v('/modules/transfers/stock/assets/js/core.js'), ['defer' => true]);
mod_script('/modules/transfers/stock/assets/js/pack.js?v=' . mod_v('/modules/transfers/stock/assets/js/pack.js'), ['defer' => true]);
// Printer v2 styles/scripts (used by box slip printing)
mod_style('/modules/transfers/stock/assets/css/printer.v2.css?v=' . mod_v('/modules/transfers/stock/assets/css/printer.v2.css'));
mod_script('/modules/transfers/stock/assets/js/printer.v2.js?v=' . mod_v('/modules/transfers/stock/assets/js/printer.v2.js'), ['defer' => true]);

// Minimal DB-backed loader for pack view compatibility
if (!function_exists('getTransferData')) {
  function getTransferData(int $transferId, bool $hydrate = true) {
    try {
      if (function_exists('cis_pdo')) { $pdo = cis_pdo(); }
      elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) { $pdo = $GLOBALS['pdo']; }
      else { return null; }

      $row = null;
      // Prefer canonical transfers table
      try {
        $stmt = $pdo->prepare('SELECT id, status, outlet_from, outlet_to, created_at, updated_at FROM transfers WHERE id = ?');
        $stmt->execute([$transferId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      } catch (Throwable $e) { /* table may not exist, try legacy */ }
      if (!$row) {
        try {
          $stmt = $pdo->prepare('SELECT transfer_id AS id, status, outlet_from, outlet_to, created_at, updated_at FROM stock_transfers WHERE transfer_id = ?');
          $stmt->execute([$transferId]);
          $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { /* legacy not present */ }
      }
      if (!$row) { return null; }

      $fromId = (string)($row['outlet_from'] ?? '');
      $toId   = (string)($row['outlet_to'] ?? '');
      $from = ['id' => $fromId, 'name' => $fromId];
      $to   = ['id' => $toId,   'name' => $toId];
      try {
        $q = $pdo->prepare('SELECT id, name FROM vend_outlets WHERE id IN (?, ?)');
        $q->execute([$fromId, $toId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $n) {
          if ((string)$n['id'] === $fromId) { $from['name'] = (string)$n['name']; }
          if ((string)$n['id'] === $toId)   { $to['name']   = (string)$n['name']; }
        }
      } catch (Throwable $e) { /* outlet names optional */ }

      $obj = (object)[
        'id' => (int)($row['id'] ?? $transferId),
        'outlet_from' => (object)$from,
        'outlet_to'   => (object)$to,
        // Start with empty products; pack UI allows adding products
        'products'    => [],
      ];

      // Optional: hydrate line items if a compatible table exists
      if ($hydrate) {
        try {
          $lines = [];
          $lineStmt = null;
          try {
            $pdo->query('SELECT 1 FROM transfer_lines LIMIT 1');
            $lineStmt = $pdo->prepare('SELECT product_id, qty_planned, qty_picked FROM transfer_lines WHERE transfer_id=?');
          } catch (Throwable $e2) {
            try {
              $pdo->query('SELECT 1 FROM stock_transfer_lines LIMIT 1');
              $lineStmt = $pdo->prepare('SELECT product_id, qty_to_transfer AS qty_planned, qty_counted AS qty_picked FROM stock_transfer_lines WHERE transfer_id=?');
            } catch (Throwable $e3) { $lineStmt = null; }
          }
          if ($lineStmt) {
            $lineStmt->execute([$transferId]);
            while ($l = $lineStmt->fetch(PDO::FETCH_ASSOC)) {
              $obj->products[] = (object)[
                'product_id' => $l['product_id'] ?? '',
                'qty_to_transfer' => (int)($l['qty_planned'] ?? 0),
                'inventory_level' => 0,
                'product_name' => '',
              ];
            }
          }

          // Preferred: hydrate from canonical or variant transfer_items schemas when available
          try {
            $pdo->query('SELECT 1 FROM transfer_items LIMIT 1');
            $fromOutletId = (string)$fromId;
            $toOutletId   = (string)$toId;

            // Try multiple possible transfer id column names
            $tidCols = ['ti.transfer_id','ti.stock_transfer_id','ti.parent_transfer_id','ti.transfer','ti.transferId','ti.id_transfer'];

            // Base candidate queries with a {{TID_COL}} placeholder for the transfer id column
            $baseCandidates = [
              // Canonical variant
              'SELECT ti.product_id,
                      ti.qty_requested AS _planned_base,
                      ti.qty_sent_total AS _picked_base,
                      COALESCE(vp.name, "") AS product_name,
                      COALESCE(vi_from.inventory_level, 0) AS inventory_from,
                      COALESCE(vi_to.inventory_level, 0) AS inventory_to
                 FROM transfer_items ti
                 LEFT JOIN vend_products vp ON vp.id = ti.product_id
                 LEFT JOIN vend_inventory vi_from ON vi_from.product_id = ti.product_id AND vi_from.outlet_id = :outlet_from
                 LEFT JOIN vend_inventory vi_to   ON vi_to.product_id   = ti.product_id AND vi_to.outlet_id   = :outlet_to
               WHERE {{TID_COL}} = :tid',
              // Alt columns: qty_planned / qty_picked
              'SELECT ti.product_id,
                      ti.qty_planned AS _planned_base,
                      ti.qty_picked AS _picked_base,
                      COALESCE(vp.name, "") AS product_name,
                      COALESCE(vi_from.inventory_level, 0) AS inventory_from,
                      COALESCE(vi_to.inventory_level, 0) AS inventory_to
                 FROM transfer_items ti
                 LEFT JOIN vend_products vp ON vp.id = ti.product_id
                 LEFT JOIN vend_inventory vi_from ON vi_from.product_id = ti.product_id AND vi_from.outlet_id = :outlet_from
                 LEFT JOIN vend_inventory vi_to   ON vi_to.product_id   = ti.product_id AND vi_to.outlet_id   = :outlet_to
               WHERE {{TID_COL}} = :tid',
              // Alt columns: qty_to_transfer present (treat as planned), no picked
              'SELECT ti.product_id,
                      ti.qty_to_transfer AS _planned_base,
                      0 AS _picked_base,
                      COALESCE(vp.name, "") AS product_name,
                      COALESCE(vi_from.inventory_level, 0) AS inventory_from,
                      COALESCE(vi_to.inventory_level, 0) AS inventory_to
                 FROM transfer_items ti
                 LEFT JOIN vend_products vp ON vp.id = ti.product_id
                 LEFT JOIN vend_inventory vi_from ON vi_from.product_id = ti.product_id AND vi_from.outlet_id = :outlet_from
                 LEFT JOIN vend_inventory vi_to   ON vi_to.product_id   = ti.product_id AND vi_to.outlet_id   = :outlet_to
               WHERE {{TID_COL}} = :tid'
            ];

            // Generic catch-all using COALESCE across common field names
            $baseCandidates[] =
        'SELECT COALESCE(ti.product_id, ti.vend_product_id, ti.sku, ti.product_sku, ti.barcode) AS product_id,
          COALESCE(ti.qty_to_transfer, ti.qty_requested, ti.qty_planned, ti.request_qty, ti.quantity, ti.qty, 0) AS _planned_base,
          COALESCE(ti.qty_sent_total, ti.qty_picked, ti.picked_qty, ti.qty_counted, 0) AS _picked_base,
          COALESCE(vp.name, ti.product_name, ti.name, ti.description, "") AS product_name,
          COALESCE(vi_from.inventory_level, 0) AS inventory_from,
          COALESCE(vi_to.inventory_level, 0) AS inventory_to
     FROM transfer_items ti
     LEFT JOIN vend_products vp ON vp.id = COALESCE(ti.product_id, ti.vend_product_id, ti.sku, ti.product_sku, ti.barcode)
     LEFT JOIN vend_inventory vi_from ON vi_from.product_id = COALESCE(ti.product_id, ti.vend_product_id, ti.sku, ti.product_sku, ti.barcode) AND vi_from.outlet_id = :outlet_from
     LEFT JOIN vend_inventory vi_to   ON vi_to.product_id   = COALESCE(ti.product_id, ti.vend_product_id, ti.sku, ti.product_sku, ti.barcode) AND vi_to.outlet_id   = :outlet_to
    WHERE {{TID_COL}} = :tid';

            $picked = null;
            foreach ($tidCols as $tidCol) {
              foreach ($baseCandidates as $sqlTpl) {
                $sql = str_replace('{{TID_COL}}', $tidCol, $sqlTpl);
                try {
                  $q = $pdo->prepare($sql);
                  $q->execute([':tid' => $transferId, ':outlet_from' => $fromOutletId, ':outlet_to' => $toOutletId]);
                  $items = $q->fetchAll(PDO::FETCH_ASSOC);
                  if ($items && is_array($items) && count($items) > 0) { $picked = $items; break 2; }
                } catch (Throwable $eVar) { /* try next combination */ }
              }
            }

            if (is_array($picked) && count($picked) > 0) {
              $obj->products = [];
              foreach ($picked as $it) {
                $basePlanned = (int)($it['_planned_base'] ?? 0);
                $basePicked  = (int)($it['_picked_base'] ?? 0);
                $planned = $basePlanned > 0 ? max(0, $basePlanned - $basePicked) : 0;
                $pid = (string)($it['product_id'] ?? '');
                $pname = trim((string)($it['product_name'] ?? ''));
                if ($pname === '') { $pname = $pid; }
                $obj->products[] = (object)[
                  'product_id'      => $pid,
                  'qty_to_transfer' => $planned,
                  'inventory_level' => (int)($it['inventory_from'] ?? $it['inventory_level'] ?? 0),
                  'product_name'    => $pname,
                ];
              }
            }
          } catch (Throwable $e4) { /* transfer_items not present; keep earlier hydration if any */ }

          // Secondary fallback: legacy stock_transfer_lines with multiple column variants
          if (empty($obj->products)) {
            $variants = [
              // product_id present, qty_to_transfer, qty_counted
              'SELECT l.product_id AS _pid,
                      l.qty_to_transfer AS _planned_base,
                      COALESCE(l.qty_counted,0) AS _picked_base,
                      COALESCE(vp.name, l.product_name) AS _pname,
                      COALESCE(vi_from.inventory_level, 0) AS inventory_from
                 FROM stock_transfer_lines l
                 LEFT JOIN vend_products vp ON vp.id = l.product_id
                 LEFT JOIN vend_inventory vi_from ON vi_from.product_id = l.product_id AND vi_from.outlet_id = :outlet_from
                WHERE {{TID_COL}} = :tid',
              // vend_product_id, qty, picked_qty
              'SELECT l.vend_product_id AS _pid,
                      l.quantity AS _planned_base,
                      COALESCE(l.picked_qty,0) AS _picked_base,
                      COALESCE(vp.name, l.name) AS _pname,
                      COALESCE(vi_from.inventory_level, 0) AS inventory_from
                 FROM stock_transfer_lines l
                 LEFT JOIN vend_products vp ON vp.id = l.vend_product_id
                 LEFT JOIN vend_inventory vi_from ON vi_from.product_id = l.vend_product_id AND vi_from.outlet_id = :outlet_from
                WHERE {{TID_COL}} = :tid',
              // sku as id, request_qty
              'SELECT l.sku AS _pid,
                      l.request_qty AS _planned_base,
                      COALESCE(l.qty_counted,0) AS _picked_base,
                      COALESCE(vp.name, l.description) AS _pname,
                      COALESCE(vi_from.inventory_level, 0) AS inventory_from
                 FROM stock_transfer_lines l
                 LEFT JOIN vend_products vp ON vp.sku = l.sku
                 LEFT JOIN vend_inventory vi_from ON vi_from.product_id = vp.id AND vi_from.outlet_id = :outlet_from
                WHERE {{TID_COL}} = :tid',
              // generic qty column
              'SELECT COALESCE(l.product_id, l.vend_product_id, l.sku, l.product_sku, l.barcode) AS _pid,
                      l.qty AS _planned_base,
                      0 AS _picked_base,
                      COALESCE(vp.name, l.product_name, l.name, l.description, l.sku) AS _pname,
                      COALESCE(vi_from.inventory_level, 0) AS inventory_from
                 FROM stock_transfer_lines l
                 LEFT JOIN vend_products vp ON vp.id = COALESCE(l.product_id, l.vend_product_id, l.sku, l.product_sku, l.barcode)
                 LEFT JOIN vend_inventory vi_from ON vi_from.product_id = COALESCE(l.product_id, l.vend_product_id, l.sku, l.product_sku, l.barcode) AND vi_from.outlet_id = :outlet_from
                WHERE {{TID_COL}} = :tid',
            ];
            $stlTidCols = ['transfer_id','stock_transfer_id','parent_transfer_id','transfer','transferId','id_transfer'];
            foreach ($variants as $sql) {
              foreach ($stlTidCols as $col) {
                try {
                  $sql2 = str_replace('{{TID_COL}}', $col, $sql);
                  $st = $pdo->prepare($sql2);
                  $st->execute([':tid' => $transferId, ':outlet_from' => $fromOutletId]);
                  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                  if ($rows && is_array($rows) && count($rows) > 0) {
                    $obj->products = [];
                    foreach ($rows as $r) {
                      $pid   = (string)($r['_pid'] ?? ''); if ($pid === '') continue;
                      $pname = (string)($r['_pname'] ?? ''); if ($pname === '') { $pname = $pid; }
                      $planned = (int)max(0, (int)($r['_planned_base'] ?? 0) - (int)($r['_picked_base'] ?? 0));
                      $obj->products[] = (object) [
                        'product_id'      => $pid,
                        'qty_to_transfer' => $planned,
                        'inventory_level' => (int)($r['inventory_from'] ?? 0),
                        'product_name'    => $pname,
                      ];
                    }
                    break 2;
                  }
                } catch (Throwable $e5) { /* try next variant */ }
              }
            }
          }
        } catch (Throwable $e) { /* ignore line hydration errors */ }
      }

      return $obj;
    } catch (Throwable $e) { return null; }
  }
}

// Attempt to hydrate basic transfer info (for diagnostics and optional UI binds)
if (!isset($transferData)) { try { $transferData = getTransferData($transferIdParam, true); } catch (Throwable $e) { $transferData = null; } }
if (!$transferData) { echo '<div class="alert alert-warning">Transfer not found.</div>'; return; }

// Hydration diagnostics: record an event with item count to transfer_logs for debugging
try {
  $tidDiag = (int)($transferData->id ?? $transferIdParam);
  $countDiag = is_iterable($transferData->products ?? null) ? count($transferData->products) : 0;
  if (function_exists('cis_pdo')) {
    $pdoDiag = cis_pdo();
    $stmtDiag = $pdoDiag->prepare('INSERT INTO transfer_logs (transfer_id, event_type, event_data, source_system, created_at) VALUES (:tid, :type, :data, :src, NOW())');
    $stmtDiag->execute([
      ':tid'  => $tidDiag ?: null,
      ':type' => 'pack_hydrate',
      ':data' => json_encode(['items_count'=>$countDiag], JSON_UNESCAPED_SLASHES),
      ':src'  => 'cis.transfers.ui',
    ]);
  }
} catch (Throwable $e) {
  // no-op; avoid breaking UI if logging fails
}
?>

<div class="stx-pack" id="stx-pack" data-transfer-id="<?= (int)($transferData->id ?? $transferIdParam) ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="stx-ajax" content="<?= mod_e(mod_asset_url('/modules/transfers/stock/ajax/handler.php')) ?>">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" id="transferID" value="<?= (int)($transferData->id ?? $transferIdParam) ?>">

  <?php
    // Use local blocks to keep this view minimal
    $___vars = ['transferId' => (int)($transferData->id ?? $transferIdParam)];
    include dirname(__DIR__) . '/blocks/pack_header.php';
    include dirname(__DIR__) . '/blocks/pack_summary.php';
    include dirname(__DIR__) . '/blocks/pack_items_table.php';
    include dirname(__DIR__) . '/blocks/pack_delivery.php';
    include dirname(__DIR__) . '/blocks/pack_notes.php';
    include dirname(__DIR__) . '/blocks/pack_finalise.php';
  ?>
</div>

<script>
  window.__STX_PACK__ = { ajaxBase: '<?= mod_e(mod_asset_url('/modules/transfers/stock/ajax/handler.php')) ?>' };
</script>

<?php mod_render_styles(); ?>
<?php mod_render_scripts(); ?>
