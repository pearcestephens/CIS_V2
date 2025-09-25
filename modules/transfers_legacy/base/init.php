<?php
/**
 * modules/transfers/base/init.php
 * Purpose: Bootstrap common transfers context.
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';

// Base constants/context for transfers
if (!defined('TRANSFERS_ROOT')) {
    define('TRANSFERS_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers');
}

// Common base functions could be added here as needed.

