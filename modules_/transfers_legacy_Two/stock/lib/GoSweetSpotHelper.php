<?php
declare(strict_types=1);

/**
 * GoSweetSpot (NZ Couriers / Freightways) integration
 * - Expects tokens at outlet level.
 * - Returns order + parcel list (with tracking); printing handled by GSS agent.
 */
final class GoSweetSpotHelper
{
    public static function createShipment(array $tokens, array $plan): array
    {
        $url = 'https://api.gosweetspot.com/shipments';
        $headers = [
            'Authorization: Bearer ' . (string)($tokens['gss_token'] ?? ''),
            'Content-Type: application/json'
        ];

        // Transform plan → GSS payload
        $payload = [
            'account'   => $tokens['gss_account'] ?? null,
            'parcels'   => self::mapParcels($plan),
            'options'   => $plan['options'] ?? [],
            'reference' => $plan['reference'] ?? null,
        ];

        $resp = self::postJson($url, $headers, $payload);
        // normalise a minimal, carrier-agnostic response shape
        return [
            'order_id'     => $resp['id'] ?? null,
            'order_number' => $resp['consignment'] ?? null,
            'parcels'      => array_map(static function($p){
                return [
                    'tracking'  => $p['tracking'] ?? null,
                    'label_url' => $p['label_url'] ?? null,
                    'weight_g'  => $p['weight_g'] ?? null,
                    'items'     => $p['items'] ?? []
                ];
            }, $resp['parcels'] ?? [])
        ];
    }

    private static function mapParcels(array $plan): array
    {
        $out = [];
        foreach (($plan['parcels'] ?? []) as $p) {
            $out[] = [
                'length_mm' => $p['dims'][0] ?? null,
                'width_mm'  => $p['dims'][1] ?? null,
                'height_mm' => $p['dims'][2] ?? null,
                'weight_g'  => $p['weight_g'] ?? null,
                'items'     => $p['items'] ?? [],
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
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("GSS API error ($code): " . ($raw ?: $err));
        }
        $j = json_decode($raw ?: '[]', true);
        return is_array($j) ? $j : [];
    }
}
