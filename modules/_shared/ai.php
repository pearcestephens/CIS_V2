<?php
declare(strict_types=1);
/**
 * modules/_shared/ai.php — OpenAI helper and simple orchestrator
 * Reads API key from getenv('OPENAI_API_KEY') or constant OPENAI_API_KEY.
 * Provides ai_chat() and ai_orchestrate_module_content() for module scaffolding.
 */

/** Check if OpenAI is configured */
function ai_enabled(): bool {
    return (bool)(getenv('OPENAI_API_KEY') ?: (defined('OPENAI_API_KEY') && OPENAI_API_KEY));
}

/** Low-level chat call */
function ai_chat(array $messages, string $model = 'gpt-4o-mini', float $temperature = 0.3, int $maxTokens = 1200): array {
    $apiKey = getenv('OPENAI_API_KEY') ?: (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
    if (!$apiKey) {
        throw new RuntimeException('OpenAI key not configured');
    }
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('OpenAI HTTP error: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($raw, true);
    if ($status >= 400) {
        $msg = $json['error']['message'] ?? ('HTTP ' . $status);
        throw new RuntimeException('OpenAI API error: ' . $msg);
    }
    return $json;
}

/**
 * Orchestrate multi-step generation for a module
 * Returns: ['index_view'=>string,'admin_view'=>string,'readme'=>string,'js'=>string,'css'=>string]
 */
function ai_orchestrate_module_content(string $slug, string $name, string $desc, array $opts = []): array {
    $purpose = trim((string)($opts['goal'] ?? 'Operational efficiency for staff'));
    $tone = trim((string)($opts['tone'] ?? 'professional, concise'));
    $entities = trim((string)($opts['entities'] ?? 'records'));
    $handlerUrl = 'https://staff.vapeshed.co.nz/modules/' . $slug . '/ajax/handler.php';
    $assetJsUrl = 'https://staff.vapeshed.co.nz/modules/' . $slug . '/assets/js/module.js';
    $constraints = "Rules: Bootstrap 5 only, semantic HTML, inside <div class=\"container\">, keep under ~250 lines per artifact, no external libs, absolute URLs for internal assets when referenced (e.g., {$handlerUrl}), accessibility (labels, aria), CIS enterprise tone (".$tone.").";

    // Step 1: Outline
    $sys = [ 'role'=>'system', 'content'=> 'You are an enterprise ERP UI generator for CIS (The Vape Shed). Produce production-ready, secure, accessible content.' ];
    $userOutline = [ 'role'=>'user', 'content'=> "Module: {$name} ({$slug})\nPurpose: {$purpose}\nEntities: {$entities}\nDescription: {$desc}\n{$constraints}\nTask: Draft a compact outline with sections for Index View, Admin View, and README summary." ];
    $o = ai_chat([$sys, $userOutline]);
    $outline = trim((string)($o['choices'][0]['message']['content'] ?? ''));

    // Step 2: Index view
    $userIndex = [ 'role'=>'user', 'content'=> "Using this outline, generate the HTML content for Index View.\nReturn only HTML snippet for inside <main>. Include: heading, filter bar, table (striped, sm), pagination, and a small help panel. Add data attributes for JS hooks. Outline:\n{$outline}\nHandler: {$handlerUrl}" ];
    $i = ai_chat([$sys, $userIndex], 'gpt-4o-mini', 0.2, 1600);
    $indexHtml = trim((string)($i['choices'][0]['message']['content'] ?? ''));

    // Step 3: Admin view + README
    $userAdmin = [ 'role'=>'user', 'content'=> "Generate two parts:\n[ADMIN]\nAdmin dashboard content with tabs (Overview, Activity, Settings).\n[README]\nConcise README section describing module purpose, setup, and endpoints ({$handlerUrl}, {$assetJsUrl})." ];
    $a = ai_chat([$sys, $userAdmin], 'gpt-4o-mini', 0.3, 1600);
    $adminAndReadme = trim((string)($a['choices'][0]['message']['content'] ?? ''));
    // naive split
    $adminHtml = $adminAndReadme;
    $readme = '';
    if (strpos($adminAndReadme, '[README]') !== false) {
        [$adminHtml, $readme] = explode('[README]', $adminAndReadme, 2);
    }

    // Optional tiny JS and CSS helpers
    $js = "// https://staff.vapeshed.co.nz/modules/{$slug}/assets/js/module.js\n// Basic hooks for filters and pagination; extend as needed.\n(()=>{ const root=document; function qs(s,c=root){return c.querySelector(s);} const handler='{$handlerUrl}'; /* TODO: wire fetches */})();\n";
    $css = ".{$slug}-help{color:#6c757d;font-size:12px}";

    return [
        'index_view' => $indexHtml,
        'admin_view' => $adminHtml,
        'readme' => $readme,
        'js' => $js,
        'css' => $css,
    ];
}
