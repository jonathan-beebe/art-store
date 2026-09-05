<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Domain\Fulfillment\FulfillmentProgress;
use App\Domain\Fulfillment\LaneFilter;
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
 * @property-read int $tally  on a row the `countedByStatus` or `countedByLane` scope selected
 * @property-read bool $started  on a row the `countedByLane` scope selected
 */
#[Fillable([
    'order_id', 'customer_id', 'seller_id', 'fulfillment_flow_id', 'status', 'carrier', 'tracking_number',
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

    /**
     * The flow this parcel started under, stamped at placement. Null on a
     * row from before the column existed.
     *
     * @return BelongsTo<FulfillmentFlow, $this>
     */
    public function fulfillmentFlow(): BelongsTo
    {
        return $this->belongsTo(FulfillmentFlow::class);
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
     * The most recently completed step on this parcel, read as one grouped
     * subquery over the whole log — {@see \App\Seller\FulfillmentLanes}
     * eager-loads it across a pane's whole page in one query. The kind
     * filter has to run inside the MAX(occurred_at) subquery `ofMany()`
     * builds, or a later `shipped`/`delivered` row on the same parcel wins
     * the grouping and the step-completed row it should have picked never
     * surfaces.
     *
     * @return HasOne<FulfillmentEvent, $this>
     */
    public function latestCompletedStep(): HasOne
    {
        return $this->hasOne(FulfillmentEvent::class)
            ->ofMany(
                ['occurred_at' => 'max'],
                fn (Builder $query): Builder => $query->where('kind', FulfillmentEventKind::StepCompleted),
            );
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
     * A fulfillment the seller portal still rolls up as ongoing business —
     * awaiting shipment, shipped, or delivered, {@see FulfillmentStatus::isLive()}.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        // Named so a caller that has also joined `orders` — which carries a
        // status column of its own — reads an unambiguous clause.
        $query->whereIn('fulfillments.status', self::liveStatuses());
    }

    /**
     * The statuses {@see live} filters to — named separately so a query
     * built inside a `whereHas` closure can reach it as a plain `whereIn`,
     * the way {@see Order::paidStatuses()} does for orders.
     *
     * @return list<FulfillmentStatus>
     */
    public static function liveStatuses(): array
    {
        return array_values(array_filter(
            FulfillmentStatus::cases(),
            fn (FulfillmentStatus $status): bool => $status->isLive(),
        ));
    }

    /**
     * A fulfillment on an order that has been paid. A fulfillment row
     * exists from the moment an order is placed, before a card is even
     * charged, so this is the paid gate every money figure reads through —
     * including one that keeps a declined or refunded parcel, since the
     * money it moved still happened.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function onPaidOrder(Builder $query): void
    {
        $query->whereHas('order', fn (Builder $orders): Builder => $orders->whereIn('status', Order::paidStatuses()));
    }

    /**
     * The parcels every seller figure counts: still live, on an order that
     * has been paid — the pair that keeps an abandoned checkout out of a
     * seller's buyers, sales, and totals.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function counted(Builder $query): void
    {
        $query->live()->onPaidOrder();
    }

    /**
     * Takes the rows this query selects for update. A transition reads the
     * status the row holds and writes a new one back over it, so the row has
     * to be held from that read until the transaction commits — otherwise two
     * consoles both read `awaiting_shipment`, both pass `transitionTo`, and
     * the second write lands on a row that has already moved. For a refund,
     * that second write breaks on `refunds.unique(fulfillment_id)` as a raw
     * `QueryException`, where the domain would otherwise refuse cleanly.
     * SQLite, which the app develops and tests on, serialises writers with a
     * database-level lock; its grammar compiles the clause away.
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
     * `refresh()` re-reads it without one. docs/spec.md §4.1 has every
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
     * Which pile of the seller's desk this parcel sits on, from progress a
     * caller already read — {@see \App\Seller\FulfillmentFlowReader}.
     */
    public function lane(FulfillmentProgress $progress): FulfillmentLane
    {
        return FulfillmentLane::of($this->status, $progress);
    }

    /**
     * One pile of the seller's desk. To ship and In progress split the
     * parcels awaiting shipment on whether a step is behind them, which is
     * the same rule {@see FulfillmentLane} reads from a loaded flow.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function inLane(Builder $query, LaneFilter $filter): void
    {
        // `orders` carries a `status` column too, so the clauses name the
        // table: a caller joining orders to sort by `placed_at` reads the
        // same lane rule without an ambiguous column.
        $status = 'fulfillments.status';

        match ($filter) {
            LaneFilter::ToShip => $query
                ->where($status, FulfillmentStatus::AwaitingShipment)
                ->whereDoesntHave('fulfillmentEvents', self::stepCompletions(...)),
            LaneFilter::InProgress => $query->where(fn (Builder $lane): Builder => $lane
                ->where(fn (Builder $started): Builder => $started
                    ->where($status, FulfillmentStatus::AwaitingShipment)
                    ->whereHas('fulfillmentEvents', self::stepCompletions(...)))
                ->orWhere($status, FulfillmentStatus::Shipped)),
            LaneFilter::Done => $query->whereIn($status, [
                FulfillmentStatus::Delivered,
                FulfillmentStatus::Declined,
                FulfillmentStatus::Refunded,
            ]),
            LaneFilter::All => $query,
        };
    }

    /**
     * One row per (status, has a completed step) pair, carrying how many
     * hold it — the two facts a lane is read from, counted in one round
     * trip.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function countedByLane(Builder $query): void
    {
        $query->select('status')
            ->selectRaw('count(*) as tally')
            ->withExists(['fulfillmentEvents as started' => self::stepCompletions(...)])
            ->groupBy('status', 'started');
    }

    /**
     * @param  Builder<FulfillmentEvent>  $events
     */
    private static function stepCompletions(Builder $events): void
    {
        $events->where('kind', FulfillmentEventKind::StepCompleted);
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
     * A route-bound fulfillment carries no relation until a controller or a
     * policy asks for one, so every caller of {@see self::isDeclinable()}
     * and {@see self::isRefundable()} loads `order` first.
     */
    private function orderHasBeenPaid(): bool
    {
        return $this->order->status->hasBeenPaid();
    }
}
