<?php
declare(strict_types=1);

// Base
define('CIS_BASE', dirname(__DIR__));                 // .../public_html
define('CIS_CORE_PATH', CIS_BASE . '/core');          // core root
define('CIS_MODULES_PATH', CIS_BASE . '/modules');    // modules root
define('CIS_TEMPLATES_PATH', CIS_BASE . '/assets/templates');

// Active template (env override allowed)
$tpl = getenv('CIS_TEMPLATE_ACTIVE') ?: 'CISv2';
define('CIS_TEMPLATE_ACTIVE', $tpl);

// Hard fail early if structure is broken
foreach ([
  'CIS_CORE_PATH'      => CIS_CORE_PATH,
  'CIS_MODULES_PATH'   => CIS_MODULES_PATH,
  'CIS_TEMPLATES_PATH' => CIS_TEMPLATES_PATH . '/' . CIS_TEMPLATE_ACTIVE,
] as $k => $v) {
  if (!is_dir($v)) {
    http_response_code(500);
    echo "[CIS] bootstrap error: missing {$k} at {$v}\n";
    exit(1);
  }
}
