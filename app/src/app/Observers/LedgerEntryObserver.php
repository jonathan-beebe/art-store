<?php

declare(strict_types=1);

namespace App\Observers;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\LedgerEntry;

/**
 * Every ledger entry is logged, and there are three places that write one —
 * holding a sale in escrow, releasing it on delivery, and paying it out. The
 * observer is the one place all three pass through, so the money trail is
 * complete without each of them saying so.
 *
 * The line carries the unit of work the entry was written inside, which is
 * how a `ledger.write` at debug joins the `order.pay` or `payout.run` it
 * belongs to.
 */
final readonly class LedgerEntryObserver
{
    public function created(LedgerEntry $entry): void
    {
        Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry', [
            'ledger_entry_id' => $entry->id,
            'seller_id' => $entry->seller_id,
            'fulfillment_id' => $entry->fulfillment_id,
            'payout_id' => $entry->payout_id,
            'type' => $entry->type->value,
            'amount_cents' => $entry->amount_cents,
        ]);
    }
}
