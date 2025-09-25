<?php
declare(strict_types=1);

namespace CIS\Core;

final class Template {
  public static function render(string $viewPhp, array $data = [], array $meta = []): void {
    // Resolve template base
    $tplBase = CIS_TEMPLATES_PATH . '/' . CIS_TEMPLATE_ACTIVE;

    // Expose $data / $meta to template scope
    extract(['data' => $data, 'meta' => $meta], EXTR_SKIP);

    // The template expects a content view file path (already rendered by module)
    // Convention: modules provide a content-only view at $viewPhp
    $contentFile = $viewPhp;

    // Main layout (in CISv2)
    $layout = $tplBase . '/layout.php';
    if (!is_file($layout)) {
      http_response_code(500);
      echo "[CIS] Template layout missing: {$layout}";
      exit(1);
    }

    require $layout;
  }
}
