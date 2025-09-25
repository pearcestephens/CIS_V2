<?php
declare(strict_types=1);
return [
  'title' => 'Receive Purchase Order',
  'subtitle' => 'Scan barcodes or update quantities',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
  ['label' => 'Purchase Orders', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index'],
    ['label' => 'Receive'],
  ],
  'layout' => 'card',
  'tabs' => [
  ['key' => 'index', 'label' => 'Overview', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index'],
  ['key' => 'receive', 'label' => 'Receive', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive', 'active' => true],
  ['key' => 'admin', 'label' => 'Admin', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin'],
  ],
  'active_tab' => 'receive',
  'page_title' => 'Receive Purchase Order — Purchase Orders — CIS',
  'meta_description' => 'Receive incoming purchase orders at outlets',
  'noindex' => false,
];
