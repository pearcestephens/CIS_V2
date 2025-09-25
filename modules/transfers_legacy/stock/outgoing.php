<?php
/**
 * Route this view through the CIS template for consistent layout, header, and breadcrumbs.
 * Keeps backward-compatible URL: https://staff.vapeshed.co.nz/modules/transfers/stock/outgoing.php
 */
declare(strict_types=1);

// Ensure module/view are set for CIS_TEMPLATE router
if (empty($_GET['module'])) { $_GET['module'] = 'transfers/stock'; }
if (empty($_GET['view'])) { $_GET['view'] = 'outgoing'; }

require $_SERVER['DOCUMENT_ROOT'] . '/modules/CIS_TEMPLATE.php';
