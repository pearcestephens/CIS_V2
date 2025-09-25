<?php
declare(strict_types=1);

/**
 * Fire-and-forget AI stream note (non-blocking, 3s timeout).
 * Usage: ai_stream('topic.key', ['foo'=>'bar']);
 */
function ai_stream(string $topic, array $payload): void
{
    $url = defined('AI_STREAM_URL') ? AI_STREAM_URL : (getenv('AI_STREAM_URL') ?: '');
    if ($url === '') return;

    $key = defined('AI_STREAM_KEY') ? AI_STREAM_KEY : (getenv('AI_STREAM_KEY') ?: '');

    $body = json_encode([
        'topic'   => $topic,
        'ts'      => time(),
        'payload' => $payload,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $headers = ['Content-Type: application/json'];
    if ($key !== '') {
        $headers[] = 'X-API-Key: ' . $key;
    }

    $ch = curl_init($url);
    if ($ch === false) return;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 3,
    ]);

    @curl_exec($ch);
    curl_close($ch);
}
