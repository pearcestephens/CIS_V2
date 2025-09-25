<?php
declare(strict_types=1);

final class PrintAgent
{
    public static function enqueue(array $labels, array $tokens, array $meta = []): array
    {
        $url     = (string)($tokens['print_agent_url'] ?? '');
        $apiKey  = (string)($tokens['print_agent_key'] ?? '');
        $printer = (string)($tokens['print_agent_printer'] ?? 'warehouse');

        if ($url === '' || $apiKey === '') {
            return ['ok' => false, 'error' => 'print_agent_not_configured'];
        }

        $payload = [
            'printer' => $printer,
            'labels'  => array_values(array_map(static function($l){
                return [
                    'url'       => (string)($l['label_url'] ?? $l['url'] ?? ''),
                    'file_type' => (string)($l['file_type'] ?? 'pdf'),
                    'copies'    => (int)($l['copies'] ?? 1),
                ];
            }, $labels)),
            'meta'    => $meta,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 12,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'print_agent_transport', 'hint' => $err];
        }
        $j = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($j)) {
            return ['ok' => false, 'error' => 'print_agent_http', 'http' => $code, 'body' => substr((string)$raw, 0, 800)];
        }
        return ['ok' => (bool)($j['ok'] ?? true), 'data' => $j];
    }
}
