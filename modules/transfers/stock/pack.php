<?php
declare(strict_types=1);

// Shared CIS bootstrap
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/config.php';

// Hardened helpers
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/JsonGuard.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/ApiResponder.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/HttpGuard.php';

// Services & policy
require_once $_SERVER['DOCUMENT_ROOT'].'/modules/transfers/stock/lib/AccessPolicy.php';

use Modules\Transfers\Stock\Services\TransfersService;
use Modules\Transfers\Stock\Lib\AccessPolicy;

// --- Auth guard (fallback) ---------------------------------------------------
if (!function_exists('requireLoggedInUser')) {
  function requireLoggedInUser(): array {
    if (empty($_SESSION['userID'])) {
      http_response_code(302);
      header('Location: /login.php');
      exit;
    }
    return ['id' => (int)$_SESSION['userID']];
  }
}
$user = requireLoggedInUser();

// --- POST: JSON API (Save Pack) ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  HttpGuard::sameOriginOr([]);                            // block cross-site
  HttpGuard::rateLimit('pack_save:'.(int)$user['id'], 60, 60);
  JsonGuard::csrfCheckOptional();
  JsonGuard::idempotencyGuard();
  $payload = JsonGuard::readJson();

  try {
    $tid = (int)($_GET['transfer'] ?? 0);
    if ($tid <= 0) {
      ApiResponder::json(['success'=>false,'error'=>'Missing ?transfer id'], 400);
    }
    if (!AccessPolicy::canAccessTransfer((int)$user['id'], $tid)) {
      ApiResponder::json(['success'=>false,'error'=>'Forbidden'], 403);
    }

    $svc = new TransfersService();
    $res = $svc->savePack($tid, $payload, (int)$user['id']);
    ApiResponder::json($res, 200);
  } catch (\Throwable $e) {
    ApiResponder::json(['success'=>false,'error'=>$e->getMessage()], 500);
  }
}

// --- GET: Render page --------------------------------------------------------
$tid = (int)($_GET['transfer'] ?? 0);
if ($tid <= 0) {
  http_response_code(400);
  echo 'Missing ?transfer id';
  exit;
}
if (!AccessPolicy::canAccessTransfer((int)$user['id'], $tid)) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$svc = new TransfersService();
$transfer = $svc->getTransfer($tid);

// Standard template shell
include $_SERVER['DOCUMENT_ROOT'].'/assets/template/html-header.php';
include $_SERVER['DOCUMENT_ROOT'].'/assets/template/header.php';
?>
<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include $_SERVER['DOCUMENT_ROOT'].'/assets/template/sidemenu.php'; ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="/modules/transfers">Transfers</a></li>
        <li class="breadcrumb-item active">Pack #<?= htmlspecialchars((string)$tid) ?></li>
      </ol>

      <div class="container-fluid">
        <?php
          $meta = ['title' => "Pack Transfer #$tid"];
          $userArr = ['first_name' => $user['first_name'] ?? '', 'last_name' => $user['last_name'] ?? ''];
          $transferArr = is_array($transfer) ? $transfer : ['id'=>$tid,'items'=>[]];
          $transfer = $transferArr; $user = $userArr;
          include __DIR__.'/views/pack.view.php';
        ?>
      </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'].'/assets/template/personalisation-menu.php'; ?>
  </div>
  <?php
    include $_SERVER['DOCUMENT_ROOT'].'/assets/template/html-footer.php';
    include $_SERVER['DOCUMENT_ROOT'].'/assets/template/footer.php';
  ?>
</body>
</html>
