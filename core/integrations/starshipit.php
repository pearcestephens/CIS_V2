<?php
declare(strict_types=1);

/**
 * File: core/integrations/starshipit.php
 * Purpose: StarshipIT (NZ Post) API client for synchronous shipment creation.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: PHP cURL extension, PDO for outlet lookup.
 */

use PDO;
use RuntimeException;

if (!class_exists('StarshipItClient')) {
    /**
     * Minimal StarshipIT API client with per-outlet credential resolution.
     */
    final class StarshipItClient
    {
        private string $apiKey;
        private string $subscriptionKey;
        private string $baseUrl;
        private int $timeout;
        private ?string $outletCode;

        private function __construct(string $apiKey, string $subscriptionKey, int $timeout = 12, ?string $outletCode = null, ?string $baseUrl = null)
        {
            $this->apiKey = trim($apiKey);
            $this->subscriptionKey = trim($subscriptionKey);
            $this->timeout = max(3, $timeout);
            $this->outletCode = $outletCode;
            $this->baseUrl = rtrim($baseUrl ?? ($_ENV['STARSHIPIT_BASE_URL'] ?? getenv('STARSHIPIT_BASE_URL') ?? 'https://api.starshipit.com'), '/');
        }

        /**
         * Resolve an API client for the specified outlet. Accepts Vend outlet code or numeric ID.
         */
        public static function forOutlet(PDO $pdo, string $outletCodeOrId): self
        {
            $row = self::lookupOutletRow($pdo, $outletCodeOrId);
            $apiKey = (string)($row['nz_post_api_key'] ?? '');
            $subscriptionKey = (string)($row['nz_post_subscription_key'] ?? '');

            if ($apiKey === '' || $subscriptionKey === '') {
                $apiKey = (string)($_ENV['NZPOST_API_KEY'] ?? getenv('NZPOST_API_KEY') ?? '');
                $subscriptionKey = (string)($_ENV['NZPOST_SUBSCRIPTION_KEY'] ?? getenv('NZPOST_SUBSCRIPTION_KEY') ?? '');
            }

            if ($apiKey === '' || $subscriptionKey === '') {
                throw new RuntimeException('StarshipIT credentials unavailable for outlet ' . $outletCodeOrId);
            }

            return new self($apiKey, $subscriptionKey, 15, (string)($row['code'] ?? $outletCodeOrId));
        }

        /**
         * Create a shipment and return the StarshipIT response envelope.
         * Destination keys align with labels_dispatch override payload.
         *
         * @param string $carrier
         * @param string $serviceCode
         * @param string $orderNumber
         * @param int $transferId
         * @param array $destination
         * @param array $packages
         * @param array $options { reference?, reprint? }
         * @return array { ok, status, data?, error?, tracks? }
         */
        public function createShipment(string $carrier, string $serviceCode, string $orderNumber, int $transferId, array $destination, array $packages, array $options = []): array
        {
            if ($this->apiKey === '' || $this->subscriptionKey === '') {
                throw new RuntimeException('StarshipIT client not configured');
            }

            $reference = (string)($options['reference'] ?? $orderNumber);

            $orderPayload = [
                'order' => [
                    'order_number' => $orderNumber,
                    'reference' => $reference,
                    'name' => (string)($destination['name'] ?? ''),
                    'company' => (string)($destination['company'] ?? ''),
                    'email' => (string)($destination['email'] ?? ''),
                    'phone' => (string)($destination['phone'] ?? ''),
                    'address1' => (string)($destination['addr1'] ?? ''),
                    'address2' => (string)($destination['addr2'] ?? ''),
                    'suburb' => (string)($destination['suburb'] ?? ''),
                    'city' => (string)($destination['city'] ?? ''),
                    'postcode' => (string)($destination['postcode'] ?? ''),
                    'country' => (string)($destination['country'] ?? 'NZ'),
                    'special_instructions' => (string)($destination['instructions'] ?? ''),
                    'metadata' => [
                        ['key' => 'transfer_id', 'value' => (string)$transferId],
                        ['key' => 'outlet_code', 'value' => (string)$this->outletCode],
                    ],
                ],
            ];

            $orderResponse = $this->request('POST', '/api/orders', $orderPayload);
            if (!($orderResponse['ok'] ?? false)) {
                return $orderResponse;
            }

            $orderId = $this->resolveOrderId($orderNumber, $orderResponse);
            if ($orderId <= 0) {
                return [
                    'ok' => false,
                    'status' => (int)($orderResponse['status'] ?? 400),
                    'error' => 'Unable to resolve StarshipIT order id',
                ];
            }

            $packagePayload = [];
            $seq = 1;
            foreach ($packages as $pkg) {
                $kg = (float)($pkg['kg'] ?? 0.0);
                if ($kg <= 0 && isset($pkg['weight_grams'])) {
                    $kg = (int)$pkg['weight_grams'] > 0 ? ((int)$pkg['weight_grams']) / 1000 : 0.0;
                }
                if ($kg <= 0) {
                    $kg = 0.5;
                }
                $packagePayload[] = [
                    'name' => (string)($pkg['name'] ?? ('Box ' . ($pkg['box_number'] ?? $seq))),
                    'weight' => round($kg, 3),
                    'height' => max(0.1, (float)($pkg['height_mm'] ?? 0) / 100),
                    'width' => max(0.1, (float)($pkg['width_mm'] ?? 0) / 100),
                    'length' => max(0.1, (float)($pkg['length_mm'] ?? 0) / 100),
                ];
                $seq++;
            }

            $shipmentPayload = [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'carrier' => $carrier,
                'carrier_service_code' => $serviceCode,
                'reprint' => (bool)($options['reprint'] ?? false),
                'packages' => $packagePayload,
            ];

            $shipmentResponse = $this->request('POST', '/api/orders/shipment', $shipmentPayload);
            if (($shipmentResponse['ok'] ?? false) && isset($shipmentResponse['data'])) {
                $shipmentResponse['tracks'] = self::extractTrackingNumbers((array)$shipmentResponse['data'], $packages);
            }

            return $shipmentResponse;
        }

        /**
         * Normalise StarshipIT tracking payload into [{box_number, tracking}].
         *
         * @param array $data
         * @param array $packages
         * @return array<int,array{box_number:int,tracking:string}>
         */
        public static function extractTrackingNumbers(array $data, array $packages = []): array
        {
            $tracks = [];
            $fallbackBox = 1;
            $packageMap = [];
            foreach ($packages as $pkg) {
                $box = (int)($pkg['box_number'] ?? $fallbackBox);
                if ($box <= 0) {
                    $box = $fallbackBox;
                }
                $packageMap[] = $box;
                $fallbackBox++;
            }

            if (isset($data['consignments']) && is_array($data['consignments'])) {
                foreach ($data['consignments'] as $consignment) {
                    if (isset($consignment['packages']) && is_array($consignment['packages'])) {
                        foreach ($consignment['packages'] as $index => $pkg) {
                            $tracking = (string)($pkg['tracking_number'] ?? $pkg['label_number'] ?? $consignment['tracking_number'] ?? '');
                            if ($tracking === '') {
                                continue;
                            }
                            $boxNumber = $pkg['box_number'] ?? ($packageMap[$index] ?? count($tracks) + 1);
                            $tracks[] = [
                                'box_number' => (int)$boxNumber,
                                'tracking' => $tracking,
                            ];
                        }
                    } elseif (!empty($consignment['tracking_number'])) {
                        $tracks[] = [
                            'box_number' => $packageMap[0] ?? 1,
                            'tracking' => (string)$consignment['tracking_number'],
                        ];
                    }
                }
            } elseif (isset($data['packages']) && is_array($data['packages'])) {
                foreach ($data['packages'] as $index => $pkg) {
                    $tracking = (string)($pkg['tracking_number'] ?? $pkg['label_number'] ?? '');
                    if ($tracking === '') {
                        continue;
                    }
                    $boxNumber = $pkg['box_number'] ?? ($packageMap[$index] ?? count($tracks) + 1);
                    $tracks[] = [
                        'box_number' => (int)$boxNumber,
                        'tracking' => $tracking,
                    ];
                }
            }

            return $tracks;
        }

        /**
         * Run an HTTP request against the StarshipIT API.
         *
         * @param string $method
         * @param string $path
         * @param array|null $body
         * @return array
         */
        private function request(string $method, string $path, ?array $body = null): array
        {
            $url = $this->baseUrl . $path;
            $headers = [
                'StarShipIT-Api-Key: ' . $this->apiKey,
                'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ];
            $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES) : null;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errNo = curl_errno($ch);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $errNo) {
                return [
                    'ok' => false,
                    'status' => $status,
                    'error' => $err ?: 'curl_error',
                ];
            }

            $decoded = json_decode((string)$raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'ok' => $status >= 200 && $status < 300,
                    'status' => $status,
                    'data' => $decoded,
                ];
            }

            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'raw' => $raw,
            ];
        }

        /** Resolve order id from response or follow-up lookup. */
        private function resolveOrderId(string $orderNumber, array $orderResponse): int
        {
            $candidates = [];
            if (isset($orderResponse['data']['order'])) {
                $candidates[] = (array)$orderResponse['data']['order'];
            } elseif (isset($orderResponse['data'])) {
                $candidates[] = (array)$orderResponse['data'];
            }
            foreach ($candidates as $candidate) {
                foreach (['order_id', 'id'] as $field) {
                    if (isset($candidate[$field]) && is_numeric($candidate[$field])) {
                        return (int)$candidate[$field];
                    }
                }
            }

            $lookup = $this->request('GET', '/api/orders?order_number=' . rawurlencode($orderNumber));
            if (($lookup['ok'] ?? false) && isset($lookup['data']['orders']) && is_array($lookup['data']['orders'])) {
                $order = reset($lookup['data']['orders']);
                if (is_array($order)) {
                    foreach (['order_id', 'id'] as $field) {
                        if (isset($order[$field]) && is_numeric($order[$field])) {
                            return (int)$order[$field];
                        }
                    }
                }
            }

            return 0;
        }

        /** Lookup outlet config row using code or id. */
        private static function lookupOutletRow(PDO $pdo, string $outletCodeOrId): array
        {
            $stm = $pdo->prepare('SELECT id, code, website_outlet_id, nz_post_api_key, nz_post_subscription_key FROM vend_outlets WHERE code = :code LIMIT 1');
            $stm->execute([':code' => $outletCodeOrId]);
            $row = $stm->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                return $row;
            }

            if (ctype_digit($outletCodeOrId)) {
                $stm = $pdo->prepare('SELECT id, code, website_outlet_id, nz_post_api_key, nz_post_subscription_key FROM vend_outlets WHERE id = :id LIMIT 1');
                $stm->execute([':id' => (int)$outletCodeOrId]);
                $row = $stm->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($row) {
                    return $row;
                }

                $stm = $pdo->prepare('SELECT id, code, website_outlet_id, nz_post_api_key, nz_post_subscription_key FROM vend_outlets WHERE website_outlet_id = :wid LIMIT 1');
                $stm->execute([':wid' => (int)$outletCodeOrId]);
                $row = $stm->fetch(PDO::FETCH_ASSOC) ?: [];
                return $row;
            }

            return [];
        }
    }
}
