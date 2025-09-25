<?php
declare(strict_types=1);

namespace CIS\Transfers\Shared;

/**
 * Canonical transfer event names to keep audit/log uniform across modules.
 */
final class Events
{
    public const MVP_LABEL_CREATED      = 'mvp_label_created';
    public const PACK_NOTE_ADDED        = 'pack_note_added';
    public const PLAN_VALIDATED         = 'plan_validated';
    public const LABEL_REPRINTED        = 'label_reprinted';
    public const LABEL_VOIDED           = 'label_voided';

    public const RECEIPT_CREATED        = 'receipt_created';
    public const RECEIPT_ITEM_SCANNED   = 'receipt_item_scanned';
    public const RECEIPT_EXCEPTION      = 'receipt_exception';

    public const MANIFEST_EXPORTED      = 'manifest_exported';
    public const TRANSFER_FORCE_CLOSED  = 'transfer_force_closed';
}
