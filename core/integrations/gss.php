<?php
declare(strict_types=1);

/**
 * File: core/integrations/gss.php
 * Purpose: GoSweetSpot (GSS) API client wrapper for synchronous label generation.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: PHP cURL extension
 */

use RuntimeException;

if (!class_exists('GSSClient')) {
    /**
     * Minimal HTTP client for the GoSweetSpot REST API.
     * Provides helpers for creating consignments and extracting tracking data.
     */
    final class GSSClient
    {
        private string $apiKey;
        private string $siteId;
        private string $supportEmail;
        private string $baseUrl;
        private int $timeout;

        public function __construct(?string $apiKey = null, ?string $siteId = null, ?string $supportEmail = null, int $timeout = 12, ?string $baseUrl = null)
        {
            $this->apiKey       = trim((string)($apiKey ?? ($_ENV['GSS_API_KEY'] ?? getenv('GSS_API_KEY') ?? '')));
            $this->siteId       = trim((string)($siteId ?? ($_ENV['GSS_ACCOUNT'] ?? getenv('GSS_ACCOUNT') ?? '')));
            $this->supportEmail = trim((string)($supportEmail ?? ($_ENV['GSS_SUPPORT_EMAIL'] ?? getenv('GSS_SUPPORT_EMAIL') ?? 'support@vapeshed.co.nz')));
            $this->timeout      = max(3, $timeout);
            $this->baseUrl      = rtrim($baseUrl ?? ($_ENV['GSS_BASE_URL'] ?? getenv('GSS_BASE_URL') ?? 'https://api.gosweetspot.com/api/'), '/') . '/';
        }

        /** Determine if credentials are present. */
        public function isConfigured(): bool
        {
            return $this->apiKey !== '';
        }

        /** Convenience static helper for health checks. */
        public static function configured(): bool
        {
            try {
                return (new self())->isConfigured();
            } catch (\Throwable $e) {
                return false;
            }
        }

        /**
         * Create a shipment in GoSweetSpot. Packages array expects keys:
         *   box_number, kg|weight_grams, length_mm, width_mm, height_mm.
         * Destination keys align with labels_dispatch override payload.
         *
         * @param array $destination
         * @param array $packages
         * @param string $reference
         * @param array $options {carrier?, signature_required?, saturday_delivery?, print_labels?}
         * @return array { ok, status, data?, error?, raw?, tracks? }
         */
        public function createShipment(array $destination, array $packages, string $reference, array $options = []): array
        {
            if (!$this->isConfigured()) {
                throw new RuntimeException('GSS access key missing');
            }

            $payload = [
                'shipments' => [
                    [
                        'reference' => $reference,
                        'ship_to' => [
                            'name' => (string)($destination['name'] ?? ''),
                            'company_name' => (string)($destination['company'] ?? ''),
                            'address1' => (string)($destination['addr1'] ?? ''),
                            'address2' => (string)($destination['addr2'] ?? ''),
                            'suburb' => (string)($destination['suburb'] ?? ''),
                            'city' => (string)($destination['city'] ?? ''),
                            'postcode' => (string)($destination['postcode'] ?? ''),
                            'country' => (string)($destination['country'] ?? 'NZ'),
                            'email' => (string)($destination['email'] ?? ''),
                            'phone' => (string)($destination['phone'] ?? ''),
                            'delivery_instructions' => (string)($destination['instructions'] ?? ''),
                        ],
                        'options' => [
                            'signature_required' => (bool)($options['signature_required'] ?? true),
                            'saturday_delivery' => (bool)($options['saturday_delivery'] ?? false),
                            'print_labels' => (bool)($options['print_labels'] ?? true),
                        ],
                        'parcels' => [],
                    ],
                ],
            ];

            if (!empty($options['carrier'])) {
                $payload['shipments'][0]['carrier'] = (string)$options['carrier'];
            }

            foreach ($packages as $pkg) {
                $boxNo = (int)($pkg['box_number'] ?? 0);
                $kg = (float)($pkg['kg'] ?? 0.0);
                if ($kg <= 0 && isset($pkg['weight_grams'])) {
                    $kg = (int)$pkg['weight_grams'] > 0 ? ((int)$pkg['weight_grams']) / 1000 : 0.0;
                }
                if ($kg <= 0) {
                    $kg = 0.5; // sensible default to avoid carrier rejection
                }

                $payload['shipments'][0]['parcels'][] = [
                    'reference' => sprintf('%s-%02d', $reference, $boxNo > 0 ? $boxNo : count($payload['shipments'][0]['parcels']) + 1),
                    'weight' => round($kg, 3),
                    'height' => max(0.01, (float)($pkg['height_mm'] ?? 0) / 1000),
                    'width' => max(0.01, (float)($pkg['width_mm'] ?? 0) / 1000),
                    'length' => max(0.01, (float)($pkg['length_mm'] ?? 0) / 1000),
                ];
            }

            $response = $this->request('POST', 'shipments', $payload);
            if (($response['ok'] ?? false) && isset($response['data'])) {
                $response['tracks'] = self::extractTrackingNumbers((array)$response['data']);
            }

            return $response;
        }

        /**
         * Extract box_number => tracking pairs from a GSS response payload.
         *
         * @param array $response
         * @return array<int,array{box_number:int,tracking:string}>
         */
        public static function extractTrackingNumbers(array $response): array
        {
            $tracks = [];
            $shipments = [];
            if (isset($response['shipments']) && is_array($response['shipments'])) {
                $shipments = $response['shipments'];
            } elseif (isset($response['data']) && is_array($response['data'])) {
                $shipments = $response['data'];
            }

            foreach ($shipments as $shipment) {
                if (!isset($shipment['parcels']) || !is_array($shipment['parcels'])) {
                    continue;
                }
                foreach ($shipment['parcels'] as $parcel) {
                    $tracking = (string)($parcel['tracking_number'] ?? $parcel['connote'] ?? '');
                    if ($tracking === '') {
                        continue;
                    }
                    $boxNo = 0;
                    if (isset($parcel['reference']) && is_string($parcel['reference']) && preg_match('/-(\d+)$/', $parcel['reference'], $m)) {
                        $boxNo = (int)$m[1];
                    } elseif (isset($parcel['box_number'])) {
                        $boxNo = (int)$parcel['box_number'];
                    }
                    $tracks[] = [
                        'box_number' => $boxNo > 0 ? $boxNo : (int)(count($tracks) + 1),
                        'tracking' => $tracking,
                    ];
                }
            }

            return $tracks;
        }

        /**
         * Execute an HTTP request against the GSS API.
         *
         * @param string $method
         * @param string $path
         * @param array|null $body
         * @return array
         */
        private function request(string $method, string $path, ?array $body = null): array
        {
            $url = $this->baseUrl . ltrim($path, '/');
            $headers = [
                'access_key: ' . $this->apiKey,
                'Content-Type: application/json; charset=utf-8',
            ];
            if ($this->siteId !== '') {
                $headers[] = 'site_id: ' . $this->siteId;
            }
            if ($this->supportEmail !== '') {
                $headers[] = 'supportemail: ' . $this->supportEmail;
            }

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
    }
}
