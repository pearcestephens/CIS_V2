<?php
/**
 * modules/transfers/stock-transfers/core/EShipClient.php
 * Minimal NZ Post eShip/Starshipit client used for catalog and label where possible.
 * Uses existing nzpost.php helpers if available, else provides stubs.
 */

declare(strict_types=1);

final class EShipClient
{
    /** Fetch order by ID/number via Starshipit API (wrapper over nzpost.php). */
    public static function getOrder($orderId = null, $orderNumber = null, int $storeId = 0)
    {
        if (function_exists('nzPOST_getOrderById')) {
            return nzPOST_getOrderById($orderId, $orderNumber, $storeId);
        }
        return false;
    }

    /** Create a shipment via Starshipit API. */
    public static function createShipment(int $orderId, string $orderNumber, string $carrier, string $serviceCode, array $packages, int $storeId = 0)
    {
        if (function_exists('nzPOST_createShipment')) {
            return nzPOST_createShipment($orderId, $orderNumber, $carrier, $serviceCode, $storeId, $packages, false);
        }
        return false;
    }
}
