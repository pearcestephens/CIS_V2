<?php
declare(strict_types=1);
/**
 * modules/transfers/stock/core/Config.php
 * Configuration values for the stock transfer workflow.
 */

final class TransferConfig
{
    /** Minutes after packing during which a user can undo/cancel. */
    public const CANCEL_GRACE_MINUTES = 30;

    /** Cron window for auto-creation (Monday 07:00 local). Documented in README. */
}
