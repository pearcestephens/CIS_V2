<?php
/**
 * File: assets/templates/cisv2/layout.php
 * Purpose: Minimal CIS v2 HTML layout used for module rendering.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: None
 */
declare(strict_types=1);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($meta['title'] ?? 'CIS', ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <main class="container">
    <?= $content ?>
  </main>
</body>
</html>
