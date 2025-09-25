<?php
/**
 * File: modules/transfers/stock/controllers/pack.php
 * Purpose: Render the pack view within the CIS v2 template shell.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: views/pack.php, views/pack.meta.php, assets/templates/cisv2
 */
declare(strict_types=1);

ob_start();
require __DIR__ . '/../views/pack.php';
$content = ob_get_clean();
$meta = require __DIR__ . '/../views/pack.meta.php';

require_once CIS_CORE_PATH . '/layout.php';
cis_render_layout($meta, $content);
