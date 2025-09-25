<?php
declare(strict_types=1);

/**
 * NZ Post (subscription + API key flavor).
 * tokens:
 *   nzpost_api_key
 *   nzpost_subscription_key
 */
final class NZPostHelper
{
    public static function createShipmentWithSubscription(array $tokens, array $plan): array
    {
        $apiKey = (string)($tokens['nzpost_api_key'] ?? '');
        $subKey = (string)($tokens['nzpost_subscription_key'] ?? '');
        if ($apiKey === '' || $subKey === '') {
            throw new RuntimeException('NZ Post credentials missing');
        }

        $url  = 'https://api.nzpost.co.nz/shipments'; // adjust if your product uses a different base
        $hdrs = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'Ocp-Apim-Subscription-Key: ' . $subKey,
        ];

        $body = [
            'reference' => $plan['reference'] ?? null,
            'parcels'   => self::mapParcels($plan),
            'options'   => $plan['options'] ?? [],
        ];

        $resp = self::postJson($url, $hdrs, $body);

        return [
            'order_id'     => $resp['shipment_id'] ?? null,
            'order_number' => $resp['reference']   ?? null,
            'parcels'      => array_map(static function($p){
                return [
                    'tracking'  => $p['tracking_number'] ?? null,
                    'label_url' => $p['label_url'] ?? null,
                    'weight_g'  => $p['weight_g'] ?? null,
                    'items'     => $p['items'] ?? [],
                ];
            }, $resp['parcels'] ?? []),
        ];
    }

    private static function mapParcels(array $plan): array
    {
        $out = [];
        foreach (($plan['parcels'] ?? []) as $p) {
            $out[] = [
                'dimensions' => [
                    'length_mm' => $p['dims'][0] ?? null,
                    'width_mm'  => $p['dims'][1] ?? null,
                    'height_mm' => $p['dims'][2] ?? null,
                ],
                'weight_g' => $p['weight_g'] ?? null,
                'items'    => $p['items'] ?? [],
            ];
        }
        return $out;
    }

    private static function postJson(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new RuntimeException("NZ Post API error ($code): " . ($raw ?: $err));
        }
        $j = json_decode($raw ?: '[]', true);
        return is_array($j) ? $j : [];
    }
}
