<?php
/**
 * modules/transfers/stock/core/Carrier/NZPostEShipClient.php
 * Direct HTTP client for NZ Post eShip (Starshipit) API with safe defaults.
 * Migrated from legacy stock-transfers core to local module scope.
 */
declare(strict_types=1);

final class NZPostEShipClient
{
    private string $apiKey;
    private string $subscriptionKey;
    private int $storeId;
    private int $timeout;

    public function __construct(?int $storeId = null, ?string $apiKey = null, ?string $subscriptionKey = null, int $timeout = 12)
    {
        $this->storeId = (int)($storeId ?? ($_SESSION['website_outlet_id'] ?? 0));
        // Prefer env; fallback to nzpost.php helpers if available
        $this->apiKey = $apiKey ?? (string)($_ENV['NZPOST_API_KEY'] ?? getenv('NZPOST_API_KEY') ?: '');
        $this->subscriptionKey = $subscriptionKey ?? (string)($_ENV['NZPOST_SUBSCRIPTION_KEY'] ?? getenv('NZPOST_SUBSCRIPTION_KEY') ?: '');
        if ($this->apiKey === '' && function_exists('nzPost_GetAPIKey')) {
            try { $this->apiKey = (string)nzPost_GetAPIKey($this->storeId); } catch (\Throwable $e) {}
        }
        if ($this->subscriptionKey === '' && function_exists('nzPost_getSubscriptionKey')) {
            try { $this->subscriptionKey = (string)nzPost_getSubscriptionKey($this->storeId); } catch (\Throwable $e) {}
        }
        $this->timeout = $timeout;
    }

    public function isConfigured(): bool
    {
        return ($this->apiKey !== '') && ($this->subscriptionKey !== '');
    }

    public static function configured(?int $storeId = null): bool
    {
        try { $c = new self($storeId); return $c->isConfigured(); } catch (\Throwable $e) { return false; }
    }

    private function headers(): array
    {
        return [
            'Content-Type: application/json',
            'StarShipIT-Api-Key: ' . $this->apiKey,
            'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
        ];
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = 'https://api.starshipit.com' . $path;
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => $this->timeout,
            // Enforce TLS verification in production
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return ['ok' => false, 'status' => $httpCode, 'error' => $err ?: 'curl_failed'];
        }
        $json = json_decode((string)$response, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'status' => $httpCode, 'error' => 'invalid_json', 'raw' => $response];
        }
        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'status' => $httpCode, 'data' => $json];
    }

    public function getOrder(?int $orderId = null, ?string $orderNumber = null): array
    {
        $q = [];
        if ($orderId !== null) $q['order_id'] = $orderId;
        if ($orderNumber !== null) $q['order_number'] = $orderNumber;
        $qs = $q ? ('?' . http_build_query($q)) : '';
        return $this->request('GET', '/api/orders' . $qs);
    }

    public function createShipment(int $orderId, string $orderNumber, string $carrier, string $serviceCode, array $packages, bool $reprint = false): array
    {
        $pkg = [];
        foreach ($packages as $p) {
            $pkg[] = [
                'weight' => (float)($p['weight'] ?? 1),
                'height' => (float)($p['height'] ?? 10) / 100,
                'width'  => (float)($p['width']  ?? 10) / 100,
                'length' => (float)($p['length'] ?? 10) / 100,
            ];
        }
        $body = [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'carrier' => $carrier,
            'carrier_service_code' => $serviceCode,
            'reprint' => (bool)$reprint,
            'packages' => $pkg,
        ];
        return $this->request('POST', '/api/orders/shipment', $body);
    }

    public function createOrder(array $order): array
    {
        $payload = ['order' => $order];
        return $this->request('POST', '/api/orders', $payload);
    }
}
