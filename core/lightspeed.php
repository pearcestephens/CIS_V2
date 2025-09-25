<?php
/**
 * File: core/lightspeed.php
 * Purpose: Provide a resilient Lightspeed (Vend) API client with retry and circuit breaker logic.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: cURL extension
 */
declare(strict_types=1);

final class LightspeedClient
{
    private string $base;
    private string $token;
    private string $breakerFile;
    private int $timeout;

    /**
     * @param string $baseUrl Base API URL, e.g. https://api.lightspeedapp.com/vend/
     * @param string $token   OAuth bearer token.
     * @param int    $timeout Request timeout in seconds.
     */
    public function __construct(string $baseUrl, string $token, int $timeout = 10)
    {
        $this->base   = rtrim($baseUrl, '/');
        $this->token  = $token;
        $this->timeout= $timeout;
        $this->breakerFile = sys_get_temp_dir() . '/ls_breaker.json';
    }

    /**
     * Issue a GET request.
     *
     * @param string $path Relative API path.
     * @param array<string, scalar> $query Optional query parameters.
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * Issue a POST request.
     *
     * @param string $path Relative API path.
     * @param array<string, mixed> $body Request payload.
     * @return array<mixed>
     */
    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, [], $body);
    }

    /**
     * Core request executor with retry/backoff and circuit breaker.
     *
     * @param string $method HTTP method.
     * @param string $path   API path.
     * @param array<string, scalar> $query Query parameters.
     * @param array<string, mixed>  $body  Request payload.
     *
     * @throws \RuntimeException on failure or breaker open.
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $query = [], array $body = []): array
    {
        $now = time();
        $state = $this->breakerState();
        if (($state['open_until'] ?? 0) > $now) {
            throw new \RuntimeException('Lightspeed breaker open; retry later');
        }

        $url = $this->base . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $attempts = 0;
        $maxAttempts = 5;
        $delay = 0.4; // seconds

        do {
            $attempts++;
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('Failed to initialise cURL');
            }

            $headers = [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers,
            ]);

            if ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
            }

            $response = curl_exec($ch);
            $error    = curl_error($ch);
            $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                $this->maybeBackoff($attempts, $maxAttempts, $delay);
                continue;
            }

            if ($code >= 200 && $code < 300) {
                $this->breakerReset();
                return $response ? (json_decode($response, true) ?? []) : [];
            }

            if ($code === 429 || ($code >= 500 && $code <= 599)) {
                $this->maybeBackoff($attempts, $maxAttempts, $delay, $code);
                continue;
            }

            $this->breakerReset();
            throw new \RuntimeException("Lightspeed error {$code}: " . substr((string) $response, 0, 500));
        } while ($attempts < $maxAttempts);

        $this->breakerTrip(min(300, (int) pow(2, $attempts)));
        throw new \RuntimeException('Lightspeed request failed after retries');
    }

    /**
     * Apply exponential backoff with jitter.
     */
    private function maybeBackoff(int $attempts, int $maxAttempts, float &$delay, int $code = 0): void
    {
        usleep((int) (($delay + mt_rand(0, 100) / 1000.0) * 1_000_000));
        $delay = min(5.0, $delay * 1.8 + 0.2);
        if ($attempts >= $maxAttempts) {
            return;
        }
    }

    /**
     * Retrieve breaker state from filesystem.
     *
     * @return array{open_until:int}|array<string,int>
     */
    private function breakerState(): array
    {
        $data = @file_get_contents($this->breakerFile);
        if (!$data) {
            return ['open_until' => 0];
        }
        $json = json_decode($data, true);
        return is_array($json) ? $json : ['open_until' => 0];
    }

    /**
     * Trip the circuit breaker for a given number of seconds.
     */
    private function breakerTrip(int $seconds): void
    {
        file_put_contents($this->breakerFile, json_encode(['open_until' => time() + $seconds]));
    }

    /**
     * Reset the breaker if it was previously tripped.
     */
    private function breakerReset(): void
    {
        if (is_file($this->breakerFile)) {
            @unlink($this->breakerFile);
        }
    }
}
