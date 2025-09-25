<?php declare(strict_types=1);
/**
 * modules/_template/bootstrap.php
 * Lightweight helper so modules can render via ModuleTemplate::render()
 */

require_once __DIR__ . '/layout.php';

if (!function_exists('cis_module_render')) {
    /**
     * Render a module page using the shared module template.
     */
    function cis_module_render(array $meta, string $content): void
    {
        \ModuleTemplate::render($meta, $content);
    }
}
