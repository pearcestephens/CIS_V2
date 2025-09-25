<?php
declare(strict_types=1);

/**
 * TransferQueueClient
 * Thin HTTPS client that talks to the Queue service public endpoints.
 *
 * Looks for ADMIN_BEARER_TOKEN (optional) and QUEUE_INTERNAL_KEY (optional).
 */
final class TransferQueueClient
{
    private string $base;
    private ?string $bearer;
    private ?string $xKey;
    private int $timeout;

    public function __construct(
        string $base = 'https://staff.vapeshed.co.nz/assets/services/queue/public',
        ?string $bearer = null,
        ?string $internalKey = null,
        int $timeoutSeconds = 8
    ) {
        $this->base   = rtrim($base, '/');
        $this->bearer = $bearer ?? (getenv('ADMIN_BEARER_TOKEN') ?: null);
        $this->xKey   = $internalKey ?? (getenv('QUEUE_INTERNAL_KEY') ?: null);
        $this->timeout = max(2, $timeoutSeconds);
    }

    public function create(int $transferPk, array $lines, ?string $idk = null): array
    {
        return $this->post('/transfer.create.php', [
            'transfer_pk'     => $transferPk,
            'lines'           => array_values($lines),
            'idempotency_key' => $idk,
        ]);
    }

    public function label(int $transferPk, array $parcelPlan, string $carrier = 'MVP', ?string $idk = null): array
    {
        return $this->post('/transfer.label.php', [
            'transfer_pk'     => $transferPk,
            'carrier'         => $carrier,
            'parcel_plan'     => $parcelPlan,
            'idempotency_key' => $idk,
        ]);
    }

    public function send(int $transferPk, array $lines, ?string $version = null, ?string $idk = null): array
    {
        return $this->post('/transfer.send.php', [
            'transfer_pk'     => $transferPk,
            'lines'           => array_values($lines),
            'ver'             => $version,
            'idempotency_key' => $idk,
        ]);
    }

    public function receive(int $transferPk, array $lines, ?string $version = null, ?string $idk = null): array
    {
        return $this->post('/transfer.receive.php', [
            'transfer_pk'     => $transferPk,
            'lines'           => array_values($lines),
            'ver'             => $version,
            'idempotency_key' => $idk,
        ]);
    }

    public function partial(int $transferPk, array $outstandingLines, ?string $idk = null): array
    {
        return $this->post('/transfer.partial.php', [
            'transfer_pk'      => $transferPk,
            'outstanding_lines'=> array_values($outstandingLines),
            'idempotency_key'  => $idk,
        ]);
    }

    public function reconcile(int $transferPk, string $strategy = 'auto', ?string $idk = null): array
    {
        return $this->post('/transfer.reconcile.php', [
            'transfer_pk'     => $transferPk,
            'strategy'        => $strategy,
            'idempotency_key' => $idk,
        ]);
    }

    public function close(int $transferPk, ?string $idk = null): array
    {
        return $this->post('/transfer.close.php', [
            'transfer_pk'     => $transferPk,
            'idempotency_key' => $idk,
        ]);
    }

    private function post(string $endpoint, array $json): array
    {
        $url = $this->base . (str_starts_with($endpoint, '/') ? $endpoint : "/$endpoint");
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($this->bearer) {
            $headers[] = 'Authorization: Bearer ' . $this->bearer;
        }
        if ($this->xKey) {
            $headers[] = 'X-Internal-Key: ' . $this->xKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => $this->timeout,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => ['code' => 'http_error', 'message' => $err ?: 'transport error', 'http' => $code]];
        }
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return ['ok' => false, 'error' => ['code' => 'bad_json', 'message' => 'Invalid JSON', 'http' => $code, 'body' => substr((string)$raw, 0, 400)]];
        }
        return $j;
    }
}
