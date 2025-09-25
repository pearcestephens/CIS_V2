<?php
declare(strict_types=1);
/**
 * modules/transfers/stock/core/QueueProducer.php
 * Purpose: Minimal, safe queue producer for stock module to emit pack/receive jobs.
 * Author: CIS Engineering
 * Last Modified: 2025-09-21
 * Dependencies: QueueConfig, app.php
 */

require_once __DIR__ . '/QueueConfig.php';

final class QueueProducer
{
    /**
     * Publish a job payload to the queue API over HTTP.
     * Uses idempotency via header X-Idempotency-Key.
     * Returns ['success'=>bool, 'status'=>int, 'body'=>array|string].
     */
    public function publish(string $type, array $payload, string $idempotencyKey, array $meta = []): array
    {
        $endpoints = [QueueConfig::PRIMARY_ENDPOINT, QueueConfig::FALLBACK_ENDPOINT];
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Idempotency-Key: ' . $idempotencyKey,
            'X-Job-Type: ' . $type,
        ];
        if (!empty($meta['request_id'])) { $headers[] = 'X-Request-ID: ' . $meta['request_id']; }
        if (!empty($meta['actor_id'])) { $headers[] = 'X-Actor-ID: ' . (string)$meta['actor_id']; }
    $body = [ 'type' => $type, 'payload' => $payload + ['meta' => $meta] ];

        $attempt = 0; $lastError = null;
        foreach ($endpoints as $ep) {
            if (!$ep) { continue; }
            $retries = QueueConfig::MAX_RETRIES;
            do {
                $attempt++;
                $res = $this->postJson($ep, $headers, $body);
                if ($res['success']) { return $res; }
                $lastError = $res;
                // Retry only on 429/5xx up to MAX_RETRIES
                $status = (int)($res['status'] ?? 0);
                if (!in_array($status, [0, 429, 500, 502, 503, 504], true)) { break; }
                usleep(150000 + random_int(0, 200000));
            } while ($retries-- > 0);
        }
        error_log('[QueueProducer] publish failed after ' . $attempt . ' attempts: ' . json_encode($lastError));
        return ['success' => false, 'status' => (int)($lastError['status'] ?? 0), 'body' => $lastError['body'] ?? null];
    }

    private function postJson(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, QueueConfig::TIMEOUT);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        // Enforce TLS, hardened defaults
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($errno !== 0) {
            return ['success' => false, 'status' => 0, 'body' => 'cURL error ' . $errno];
        }
        $data = json_decode((string)$out, true);
        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'status' => $status, 'body' => $data ?? $out];
        }
        return ['success' => false, 'status' => $status, 'body' => $data ?? $out];
    }
}
