<?php
declare(strict_types=1);

/**
 * Resolve per-outlet tokens from vend_outlets.
 * Columns used:
 *   gss_token
 *   nz_post_api_key
 *   nz_post_subscription_key
 *
 * Print Agent config remains global via env/constants, but you can
 * also override per-outlet by extending this if you later add columns.
 */
final class TokensResolver
{
    public static function forOutlet(string $outletId): array
    {
        $pdo = db();
        $q = $pdo->prepare("
            SELECT
              id,
              name,
              gss_token,
              nz_post_api_key,
              nz_post_subscription_key
            FROM vend_outlets
            WHERE id = :id
            LIMIT 1
        ");
        $q->execute([':id' => $outletId]);
        $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];

        // Print Agent (pooling): global env/defines (no local printing here)
        $printUrl     = getenv('PRINT_AGENT_URL')   ?: (defined('PRINT_AGENT_URL')   ? PRINT_AGENT_URL   : '');
        $printKey     = getenv('PRINT_AGENT_KEY')   ?: (defined('PRINT_AGENT_KEY')   ? PRINT_AGENT_KEY   : '');
        $printPrinter = getenv('PRINT_AGENT_PRINTER') ?: (defined('PRINT_AGENT_PRINTER') ? PRINT_AGENT_PRINTER : 'warehouse');

        return [
            'outlet_id'                => (string)($row['id'] ?? $outletId),
            'outlet_name'              => (string)($row['name'] ?? ''),
            // GSS
            'gss_token'                => (string)($row['gss_token'] ?? ''),
            'gss_account'              => (string)($row['name'] ?? $outletId), // reasonable default
            // NZ Post (subscription + api key model)
            'nzpost_api_key'           => (string)($row['nz_post_api_key'] ?? ''),
            'nzpost_subscription_key'  => (string)($row['nz_post_subscription_key'] ?? ''),
            // Print Agent
            'print_agent_url'          => (string)$printUrl,
            'print_agent_key'          => (string)$printKey,
            'print_agent_printer'      => (string)$printPrinter,
        ];
    }
}
