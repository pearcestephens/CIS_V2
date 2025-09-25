<?php
declare(strict_types=1);
/**
 * File: modules/transfers/stock/views/components.php
 * Purpose: Components Gallery — list and preview all module blocks and usage counts.
 * Standalone module-scoped page (no external template deps).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock/lib/view_helpers.php';

// Discover blocks in this module
$doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$blocksDir = $doc . '/modules/transfers/stock/blocks';
$viewsDir  = $doc . '/modules/transfers/stock/views';
$files = [];
if (is_dir($blocksDir)) {
  foreach (scandir($blocksDir) as $f) {
    if ($f === '.' || $f === '..') continue;
    if (substr($f, -4) !== '.php') continue;
    $files[] = $f;
  }
}

// Compute naive usage counts by grepping include statements in this module
function _usage_count(string $blockName, string $viewsDir): int {
  $pattern = '/(include|require).*' . preg_quote($blockName, '/') . '/i';
  $count = 0;
  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
  foreach ($rii as $file) {
    if ($file->isDir()) continue;
    if (substr($file->getFilename(), -4) !== '.php') continue;
    $c = @file_get_contents($file->getPathname()); if ($c === false) continue;
    $count += preg_match_all($pattern, $c) ?: 0;
  }
  return $count;
}

// Minimal styles
mod_style('/modules/_shared/assets/css/cis-tokens.css?v=' . mod_v('/modules/_shared/assets/css/cis-tokens.css'));
mod_style('/modules/_shared/assets/css/cis-utilities.css?v=' . mod_v('/modules/_shared/assets/css/cis-utilities.css'));
mod_style('/modules/transfers/stock/assets/css/stx-ui.css?v=' . mod_v('/modules/transfers/stock/assets/css/stx-ui.css'));
mod_style('/modules/transfers/stock/assets/css/stock.css?v=' . mod_v('/modules/transfers/stock/assets/css/stock.css'));

?><div class="container my-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h4 mb-0">Transfers/Stock — Components Gallery</h2>
    <a class="btn btn-sm btn-outline-primary" href="<?= mod_e(mod_asset_url('/modules/transfers/stock/views/dashboard.php')) ?>">Back to Dashboard</a>
  </div>
  <div class="row">
    <?php if (empty($files)): ?>
      <div class="col-12"><div class="alert alert-warning">No blocks found.</div></div>
    <?php else: foreach ($files as $f): $usage=_usage_count($f, $viewsDir); ?>
      <div class="col-12 col-md-6 col-lg-4 mb-3">
        <div class="card h-100 shadow-sm">
          <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong class="small"><?= mod_e($f) ?></strong>
            <span class="badge badge-info">Used <?= (int)$usage ?>x</span>
          </div>
          <div class="card-body" style="min-height:160px; overflow:auto;">
            <div class="small text-muted mb-2">Preview</div>
            <div class="border rounded p-2">
              <?php
                // Try sandboxed include with minimal known vars
                $path = $blocksDir . '/' . $f;
                try { include $path; } catch (Throwable $e) { echo '<div class="text-danger">Error rendering: ' . mod_e($e->getMessage()) . '</div>'; }
              ?>
            </div>
          </div>
          <div class="card-footer py-2">
            <small class="text-muted">Path: /modules/transfers/stock/blocks/<?= mod_e($f) ?></small>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php mod_render_styles(); ?>
<?php mod_render_scripts(); ?>
