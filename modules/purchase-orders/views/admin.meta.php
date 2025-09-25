<?php
declare(strict_types=1);
return [
  'title' => 'PO Admin Dashboard',
  'subtitle' => 'Receipts, events, queue, evidence',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
  ['label' => 'Purchase Orders', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index'],
    ['label' => 'Admin'],
  ],
  'layout' => 'card',
  'tabs' => [
  ['key' => 'index', 'label' => 'Overview', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index'],
  ['key' => 'admin', 'label' => 'Admin', 'href' => 'https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin', 'active' => true],
  ],
  'active_tab' => 'admin',
  'page_title' => 'PO Admin — Purchase Orders — CIS',
  'meta_description' => '',
  'noindex' => false,
];
