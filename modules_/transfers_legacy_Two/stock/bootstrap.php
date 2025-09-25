<?php declare(strict_types=1);

use CISV2\Modules\Transfers\Stock\ServiceRegistry;
use Modules\Template\ModuleTemplate;
use Modules\Template\Responder;

require_once __DIR__ . '/../_template/ModuleTemplate.php';
require_once __DIR__ . '/../_template/Responder.php';
require_once __DIR__ . '/src/ServiceRegistry.php';
require_once __DIR__ . '/src/TransferPackingService.php';

if (!function_exists('transfers_stock_services')) {
    function transfers_stock_services(): ServiceRegistry
    {
        static $registry;
        if ($registry instanceof ServiceRegistry) {
            return $registry;
        }
        $registry = new ServiceRegistry(db_rw());
        return $registry;
    }
}

if (!function_exists('transfers_stock_render')) {
    function transfers_stock_render(array $meta, string $content): void
    {
        ModuleTemplate::render($meta, $content);
    }
}

if (!function_exists('transfers_stock_responder')) {
    function transfers_stock_responder(): Responder
    {
        return new Responder();
    }
}
