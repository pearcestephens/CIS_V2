<?php
declare(strict_types=1);
/**
 * modules/transfers/stock/core/States.php
 * Transfer state model and transitions aligned with Vend Consignment lifecycle.
 */

final class TransferState
{
    public const DRAFT                = 'draft';
    public const PACKING              = 'packing';
    public const READY_TO_SEND        = 'ready_to_send';
    public const SENT                 = 'sent';
    public const IN_TRANSIT           = 'in_transit';
    public const RECEIVING            = 'receiving';
    public const PARTIALLY_RECEIVED   = 'partially_received';
    public const RECEIVED             = 'received';
    public const CANCELED             = 'canceled';

    /**
     * Allowed state transitions.
     * @return array<string, string[]>
     */
    public static function transitions(): array
    {
        return [
            self::DRAFT => [self::PACKING, self::CANCELED],
            self::PACKING => [self::READY_TO_SEND, self::CANCELED],
            self::READY_TO_SEND => [self::SENT, self::CANCELED],
            self::SENT => [self::IN_TRANSIT, self::RECEIVING, self::CANCELED],
            self::IN_TRANSIT => [self::RECEIVING, self::CANCELED],
            self::RECEIVING => [self::PARTIALLY_RECEIVED, self::RECEIVED],
            self::PARTIALLY_RECEIVED => [self::RECEIVING, self::RECEIVED],
            self::RECEIVED => [],
            self::CANCELED => [],
        ];
    }

    /**
     * Validate transition.
     */
    public static function canTransition(string $from, string $to): bool
    {
        $map = self::transitions();
        return isset($map[$from]) && in_array($to, $map[$from], true);
    }
}
