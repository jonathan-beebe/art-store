<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Customer;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an anonymous customer nothing has claimed and nothing hangs off
 * of, once it has sat past the retention window. `ResolveCustomerIdentity`
 * defers minting a row until an event is worth tracking under one, but that
 * still leaves noise: a listing viewed once and never returned to, a store
 * page a crawler hit. This is where that noise is cleaned up, so the count
 * of anonymous customers reflects visitors still worth watching.
 *
 * A row is swept only once it owns nothing a merge or a page reads back: no
 * cart items, no favorites, no orders, no conversations, no notifications,
 * no `customer_blocks` row, and no `customer_merges` row on either side —
 * the last of which also protects a merge's stale-cookie trail from being
 * swept out from under it. Deleting the row cascades to its own empty cart,
 * the one thing an anonymous customer with nothing else can still hold.
 */
final readonly class SweepAnonymousCustomers
{
    private const int BATCH_SIZE = 500;

    public function __invoke(DateTimeImmutable $asOf, int $retentionDays): int
    {
        Story::asSystem();

        $cutoff = $asOf->modify("-{$retentionDays} days");

        return Story::for(StoryEvent::CustomerSweep)->tell('sweeping anonymous customers nobody claimed', [
            'cutoff' => $cutoff->format(DateTimeImmutable::ATOM),
        ], function (Story $story) use ($cutoff): int {
            $deleted = 0;

            do {
                $batchDeleted = DB::transaction(fn (): int => $this->sweepBatch($cutoff));
                $deleted += $batchDeleted;
            } while ($batchDeleted === self::BATCH_SIZE);

            $story->did('swept the anonymous customers nobody claimed', ['deleted_count' => $deleted]);

            return $deleted;
        });
    }

    private function sweepBatch(DateTimeImmutable $cutoff): int
    {
        $ids = $this->ownsNothing($cutoff)->limit(self::BATCH_SIZE)->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        Customer::query()->whereIn('id', $ids)->delete();

        return $ids->count();
    }

    /**
     * @return Builder<Customer>
     */
    private function ownsNothing(DateTimeImmutable $cutoff): Builder
    {
        return Customer::query()
            ->whereNull('email')
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('cartItems')
            ->whereDoesntHave('favorites')
            ->whereDoesntHave('orders')
            ->whereDoesntHave('conversations')
            ->whereDoesntHave('notifications')
            ->whereDoesntHave('blocks')
            ->whereDoesntHave('mergesAsCustomer')
            ->whereDoesntHave('mergesAsAnonymous');
    }
}
