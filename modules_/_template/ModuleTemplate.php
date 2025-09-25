<?php declare(strict_types=1);

namespace Modules\Template;

final class ModuleTemplate
{
    private const DEFAULT_META = [
        'title'      => 'CISV2',
        'breadcrumb' => [],
        'assets'     => [
            'css' => [],
            'js'  => [],
        ],
    ];

    /**
     * @param array $meta    metadata (title, breadcrumb, assets)
     * @param string $content rendered page markup
     */
    public static function render(array $meta, string $content): void
    {
        $merged = array_replace_recursive(self::DEFAULT_META, $meta);

        $page = self::captureLayout($merged, $content);
        echo $page;
    }

    private static function captureLayout(array $meta, string $content): string
    {
        $layoutPath = __DIR__ . '/layout.php';
        if (!is_file($layoutPath)) {
            throw new \RuntimeException('Module layout missing: ' . $layoutPath);
        }

        ob_start();
        require $layoutPath;
        return ob_get_clean() ?: '';
    }
}
