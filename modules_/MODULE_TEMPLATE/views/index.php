<?php
declare(strict_types=1);
/** index.php — __MODULE_NAME__ user view */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <title>__MODULE_NAME__ — CIS</title>
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/assets/css/module.css">
</head>
<body>
  <div class="container mt-3" data-module="__MODULE_SLUG__">
    <div class="__MODULE_SLUG__-card">
      <div class="__MODULE_SLUG__-header">
        <h3 class="mb-0">__MODULE_NAME__</h3>
        <div class="__MODULE_SLUG__-actions">
          <button class="btn btn-sm btn-primary" data-action="ping">Ping</button>
        </div>
      </div>
      <p class="text-muted">This is a starter view. Use DevTools Network tab to observe POST calls.</p>
    </div>
  </div>
  <script src="https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/assets/js/module.js" defer></script>
</body>
</html>
