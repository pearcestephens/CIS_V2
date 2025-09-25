<?php declare(strict_types=1);
/**
 * Standard CISV2 chrome: header -> sidebar -> container -> footer.
 * Expects: $meta (array), $content (string)
 */
$meta = $meta ?? [
  'title'      => 'Page',
  'breadcrumb' => [],
  'assets'     => [],
];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($meta['title'] ?? 'CISV2', ENT_QUOTES) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap (assumes you already serve these; swap to your asset paths) -->
  <link rel="stylesheet" href="/assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/cisv2.css">
  <style>
    body { background:#f6f7fb; }
    .cis-shell { display:flex; min-height:100vh; }
    .cis-sidebar {
      width: 260px; flex:0 0 260px; background:#111827; color:#cbd5e1;
      position:sticky; top:0; height:100vh; overflow:auto;
    }
    .cis-sidebar a { color:#cbd5e1; text-decoration:none; display:block; padding:.65rem 1rem; border-radius:.375rem; }
    .cis-sidebar a.active, .cis-sidebar a:hover { background:#1f2937; }
    .cis-main { flex:1; min-width:0; }
    .cis-header { background:#ffffff; border-bottom:1px solid #e5e7eb; }
    .cis-container { padding: 1.25rem; }
    .breadcrumb { margin-bottom: 0; }
  </style>
</head>
<body>
  <div class="cis-shell">
    <aside class="cis-sidebar">
      <?php require __DIR__ . '/sidebar.php'; ?>
    </aside>

    <main class="cis-main">
      <header class="cis-header">
        <div class="container-fluid d-flex align-items-center justify-content-between py-3">
          <div>
            <h1 class="h4 mb-1"><?= htmlspecialchars($meta['title'] ?? 'CISV2', ENT_QUOTES) ?></h1>
            <?php if (!empty($meta['breadcrumb'])): ?>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                  <?php foreach ($meta['breadcrumb'] as $crumb): ?>
                    <li class="breadcrumb-item">
                      <?php if (!empty($crumb['href'])): ?>
                        <a href="<?= htmlspecialchars($crumb['href'], ENT_QUOTES) ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES) ?></a>
                      <?php else: ?>
                        <?= htmlspecialchars($crumb['label'], ENT_QUOTES) ?>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              </nav>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <section class="cis-container">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12 col-xl-12">
              <?= $content ?? '' ?>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="/assets/vendor/bootstrap.bundle.min.js"></script>
  <?php if (!empty($meta['assets']['js'])) foreach ($meta['assets']['js'] as $js) echo '<script src="'.htmlspecialchars($js,ENT_QUOTES).'"></script>'; ?>
</body>
</html>
