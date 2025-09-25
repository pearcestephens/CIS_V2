<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/_shared/ai.php';

function mt_ai_generate(): array {
    if (!function_exists('ai_enabled') || !ai_enabled()) {
        throw new RuntimeException('AI not configured');
    }
    $goal = trim((string)($_POST['goal'] ?? ''));
    $tone = trim((string)($_POST['tone'] ?? 'professional, concise'));
    $entities = trim((string)($_POST['entities'] ?? 'records'));
    $slug = basename(dirname(__DIR__, 2)); // modules/<slug>/ajax/actions
    $name = '__MODULE_NAME__';
    $desc = 'Auto-generated content for module';
    $res = ai_orchestrate_module_content($slug, $name, $desc, [
        'goal' => $goal, 'tone' => $tone, 'entities' => $entities,
    ]);
    return $res;
}
