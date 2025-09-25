<?php
/**
 * modules/transfers/stock/core/Carrier/GSSClient.php
 * Minimal client for GoSweetSpot.
 */
declare(strict_types=1);

final class GSSClient
{
    private string $apiKey;
    private string $account; // Optional: maps to GSS site_id header when set
    private string $supportEmail = '';
    private string $baseUrl = 'https://api.gosweetspot.com/api/';

    public function __construct(?string $apiKey = null, ?string $account = null, ?string $supportEmail = null)
    {
        $this->apiKey = $apiKey ?? (string)($_ENV['GSS_API_KEY'] ?? getenv('GSS_API_KEY') ?: '');
        $this->account = $account ?? (string)($_ENV['GSS_ACCOUNT'] ?? getenv('GSS_ACCOUNT') ?: '');
        $this->supportEmail = $supportEmail ?? (string)($_ENV['GSS_SUPPORT_EMAIL'] ?? getenv('GSS_SUPPORT_EMAIL') ?: '');

        if ($this->apiKey === '' && function_exists('gss_get_api_key')) {
            try { $this->apiKey = (string)gss_get_api_key(); } catch (\Throwable $e) {}
        }
        if ($this->account === '' && function_exists('gss_get_account')) {
            try { $this->account = (string)gss_get_account(); } catch (\Throwable $e) {}
        }
    }

    public function isConfigured(): bool { return $this->apiKey !== ''; }
    public static function configured(): bool { try { $c = new self(); return $c->isConfigured(); } catch (\Throwable $e) { return false; } }

    private function request(string $method, string $path, array $opts = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $ch = curl_init();
        $headers = [
            'access_key: ' . $this->apiKey,
            'Content-Type: application/json; charset=utf-8'
        ];
        if ($this->account !== '') { $headers[] = 'site_id: ' . $this->account; }
        if ($this->supportEmail !== '') { $headers[] = 'supportemail: ' . $this->supportEmail; }
        $body = $opts['body'] ?? null;
        if (is_array($body)) { $body = json_encode($body, JSON_UNESCAPED_SLASHES); }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err   = curl_error($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $errNo) { return ['ok'=>false, 'status'=>$code, 'error'=>$err ?: 'curl_error']; }
        $data = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE) { return ['ok' => $code >= 200 && $code < 300, 'status'=>$code, 'data'=>$data]; }
        return ['ok' => $code >= 200 && $code < 300, 'status'=>$code, 'raw'=>$resp];
    }

    public function createShipment(array $payload): array { return $this->request('POST', 'shipments', ['body'=>$payload]); }
    public function getShipment(string $shipmentId): array { return $this->request('GET', 'shipments?shipments=' . rawurlencode($shipmentId)); }
    public function printLabelByConnote(string $connote): array { return $this->request('POST', 'labels?connote=' . rawurlencode($connote), ['body'=>'']); }
}
