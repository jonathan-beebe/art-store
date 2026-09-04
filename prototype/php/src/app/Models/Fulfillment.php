<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Escrow\PlatformFees;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Domain\Fulfillment\FulfillmentProgress;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\FulfillmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Override;

/**
 * @property-read Order $order
 * @property-read Seller $seller
 * @property-read int $tally  only on a row the `countedByStatus` scope selected
 */
#[Fillable([
    'order_id', 'customer_id', 'seller_id', 'status', 'carrier', 'tracking_number',
    'shipped_at', 'delivered_at', 'subtotal_cents', 'fee_cents', 'net_cents',
])]
class Fulfillment extends Model
{
    /** @use HasFactory<FulfillmentFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'ful';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'subtotal_cents' => 'integer',
            'fee_cents' => 'integer',
            'net_cents' => 'integer',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Everything that has happened to this parcel, oldest first.
     *
     * @return HasMany<FulfillmentEvent, $this>
     */
    public function fulfillmentEvents(): HasMany
    {
        return $this->hasMany(FulfillmentEvent::class)->inOrder();
    }

    /**
     * The refund that settled this fulfillment. There is at most one: a
     * refund is always the whole subtotal, so a second would send the money
     * twice.
     *
     * @return HasOne<Refund, $this>
     */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * The admin fulfillments list, narrowed to one status. A null filter adds
     * no clause, which is what the console's "All statuses" submits.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofStatus(Builder $query, ?FulfillmentStatus $status): void
    {
        if ($status instanceof FulfillmentStatus) {
            $query->where('status', $status);
        }
    }

    /**
     * The same list narrowed to one seller.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofSeller(Builder $query, ?string $sellerId): void
    {
        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }
    }

    /**
     * Takes the rows this query selects for update. A transition reads the
     * status the row holds and writes a new one back over it, so the row has
     * to be held from that read until the transaction commits — otherwise two
     * consoles both read `awaiting_shipment`, both pass `transitionTo`, and
     * the second write lands on a row that has already moved (and, for a
     * refund, breaks on `refunds.unique(fulfillment_id)` as a raw
     * `QueryException` instead of a refusal). SQLite, which the prototype
     * develops and tests on, has no row lock and serialises writers instead;
     * its grammar compiles the clause away.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function lockedForTransition(Builder $query): void
    {
        $query->lockForUpdate();
    }

    /**
     * Re-reads this row for update inside the caller's transaction, the way
     * `refresh()` re-reads it without one. docs/alignment.md §4.1 has every
     * transition judged inside the transaction that writes, so the four
     * actions that move a fulfillment call this first and read the status off
     * what it hands back.
     */
    public function takeForTransition(): static
    {
        /** @var static $locked */
        $locked = $this->newQuery()->whereKey($this->getKey())->lockedForTransition()->sole();

        return $this->setRawAttributes($locked->getAttributes(), sync: true);
    }

    /**
     * One row per status the table holds, carrying how many hold it — the
     * dashboard's fulfillment tally reads this the way `Listing::ofStatus`'s
     * sibling scope feeds the listing one.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function countedByStatus(Builder $query): void
    {
        $query->select('status')
            ->selectRaw('count(*) as tally')
            ->groupBy('status');
    }

    /**
     * The same tally, for `/admin`'s fulfillment count.
     *
     * @return array<string, int> status value => count
     */
    public static function platformCountsByStatus(): array
    {
        $counts = [];

        foreach (self::query()->countedByStatus()->get() as $row) {
            $counts[$row->status->value] = $row->tally;
        }

        return $counts;
    }

    /**
     * What the platform earned and gave back in fees, across every
     * fulfillment there is — one read, folded by the pure
     * {@see PlatformFees}.
     */
    public static function platformFees(): PlatformFees
    {
        return PlatformFees::from(array_values(
            self::query()
                ->select('status', 'fee_cents')
                ->get()
                ->map(fn (self $fulfillment): array => [
                    'status' => $fulfillment->status,
                    'feeCents' => $fulfillment->fee_cents,
                ])
                ->all(),
        ));
    }

    /**
     * The flow this parcel ships by: the one the first of the seller's own
     * lines names, and the seller's default flow when none does.
     */
    public function flowInEffect(): ?FulfillmentFlow
    {
        $this->loadMissing(['order.items.listing.fulfillmentFlow', 'seller.defaultFulfillmentFlow']);

        return $this->flowNamedByAListing() ?? $this->seller->defaultFulfillmentFlow;
    }

    /**
     * The steps of that flow, as the pure core reads them.
     *
     * @return list<FlowStep>
     */
    public function flowSteps(): array
    {
        $flow = $this->flowInEffect();

        return $flow instanceof FulfillmentFlow ? $flow->loadMissing('steps')->flowSteps() : [];
    }

    public function progress(): FulfillmentProgress
    {
        return FulfillmentProgress::of($this->flowSteps(), $this->completedStepIds());
    }

    public function lane(): FulfillmentLane
    {
        return FulfillmentLane::of($this->status, $this->progress());
    }

    public function subtotal(): Money
    {
        return Money::fromCents($this->subtotal_cents);
    }

    public function fee(): Money
    {
        return Money::fromCents($this->fee_cents);
    }

    public function net(): Money
    {
        return Money::fromCents($this->net_cents);
    }

    /**
     * Whether the seller can still turn this parcel down. The order behind it
     * has to have been paid, because a decline sends money back.
     */
    public function isDeclinable(): bool
    {
        return $this->status->canTransitionTo(FulfillmentStatus::Declined) && $this->orderHasBeenPaid();
    }

    /**
     * Whether an admin can still refund this parcel — from awaiting shipment
     * for a seller who never answered, and from shipped or delivered as a
     * dispute outcome.
     */
    public function isRefundable(): bool
    {
        return $this->status->canTransitionTo(FulfillmentStatus::Refunded) && $this->orderHasBeenPaid();
    }

    /**
     * Reads the order through `loadMissing` so a policy or a view asking
     * about a route-bound fulfillment does not trip the lazy-load guard.
     */
    private function orderHasBeenPaid(): bool
    {
        return $this->loadMissing('order')->order->status->hasBeenPaid();
    }

    private function flowNamedByAListing(): ?FulfillmentFlow
    {
        foreach ($this->order->items as $item) {
            if ($item->seller_id === $this->seller_id && $item->listing->fulfillmentFlow instanceof FulfillmentFlow) {
                return $item->listing->fulfillmentFlow;
            }
        }

        return null;
    }

    /**
     * One entry per completion the log holds, carrying the step it named. A
     * step the seller has since removed leaves a null: the row survives with
     * its `step_label`, so the parcel still counts as started.
     *
     * @return list<string|null>
     */
    private function completedStepIds(): array
    {
        return array_values($this->loadMissing('fulfillmentEvents')->fulfillmentEvents
            ->where('kind', FulfillmentEventKind::StepCompleted)
            ->map(fn (FulfillmentEvent $event): ?string => $event->fulfillment_flow_step_id)
            ->all());
    }
}
