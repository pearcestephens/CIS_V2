<?php
declare(strict_types=1);
/**
 * modules/transfers/stock/core/QueueConfig.php
 * Purpose: Configuration for queue job production from Stock module.
 * Author: CIS Engineering
 * Last Modified: 2025-09-21
 * Dependencies: app.php (bootstrap), cURL extension
 */

final class QueueConfig
{
    /** Enable HTTP producer that posts jobs to the queue API. */
    public const ENABLE_HTTP_PRODUCER = true;

    /** Gate: publish a queue job on finalize/markReady (audit-only). */
    public const ENABLE_FINALIZE_QUEUE = false; // set true to enable

    /** When ENABLE_FINALIZE_QUEUE is true, use an allowed job type. */
    public const FINALIZE_JOB_TYPE = 'update_consignment';

    /** Primary queue enqueue endpoint (admin-protected). */
    public const PRIMARY_ENDPOINT = 'https://staff.vapeshed.co.nz/assets/services/queue/public/job.php';

    /** Fallback endpoint (same service path). */
    public const FALLBACK_ENDPOINT = 'https://staff.vapeshed.co.nz/assets/services/queue/public/job.php';

    /** Timeout seconds for HTTP calls. */
    public const TIMEOUT = 6;

    /** Max retries for transient failures. */
    public const MAX_RETRIES = 2;
}
