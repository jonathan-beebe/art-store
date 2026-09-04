<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Orders\OrderPlacementPlan;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\PlaceableLine;
use App\Domain\Payments\PaymentStatus;
use App\Models\Concerns\HasPrefixedUlid;
use App\Support\Orders\PlaceableLineBuilder;
use Database\Factories\OrderFactory;
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
 * @property-read Customer $customer
 * @property-read int $tally  only on a row the `countedByStatus` scope selected
 */
#[Fillable([
    'customer_id', 'email', 'status', 'shipping_name', 'shipping_line1', 'shipping_line2',
    'shipping_city', 'shipping_region', 'shipping_postal_code', 'shipping_country',
    'subtotal_cents', 'total_cents', 'refunded_cents', 'placed_at', 'finalized_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'ord';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_cents' => 'integer',
            'total_cents' => 'integer',
            'refunded_cents' => 'integer',
            'placed_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The order's own lines, in the order they were placed in: `created_at`
     * places them, `id` — a ULID minted the same moment — breaks a tie
     * within the same request. {@see Fulfillment::flowNamedByAListing()}
     * reads the first as the listing a parcel ships by.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<Fulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * The attempt a page reports on: after a decline and a retry, the latest
     * one is what the shopper is looking at.
     *
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->payments()->one()->latestOfMany('processed_at');
    }

    /**
     * The attempt that put money on this order, which is what a refund names
     * as the charge it reverses.
     *
     * @return HasOne<Payment, $this>
     */
    public function approvedPayment(): HasOne
    {
        return $this->payments()->one()->ofMany(
            ['processed_at' => 'max'],
            fn (Builder $query) => $query->where('status', PaymentStatus::Approved),
        );
    }

    /**
     * The shipping address as a parcel label prints it, one line per row,
     * with the lines this order left empty dropped. A blank city drops
     * straight to the region and postal code, with no leading comma.
     *
     * @return list<string>
     */
    public function shippingAddressLines(): array
    {
        $regionAndPostal = trim(implode(' ', array_filter([$this->shipping_region, $this->shipping_postal_code])));
        $cityLine = implode(', ', array_filter([$this->shipping_city, $regionAndPostal]));

        $lines = [
            $this->shipping_name,
            $this->shipping_line1,
            $this->shipping_line2,
            $cityLine,
            $this->shipping_country,
        ];

        return array_values(array_filter(
            array_map(fn (?string $line): string => trim($line ?? ''), $lines),
            fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * How placement judges this order's items against the listings behind
     * them, as the rows stand right now. A retry after a decline calls this
     * before it retakes stock — against rows it holds for update — so an item
     * that went stale while the card sat declined is refused rather than sold
     * a second time out from under someone else.
     */
    public function placementPlan(): OrderPlacementPlan
    {
        return OrderPlacementPlan::for(array_values($this->items->map(
            fn (OrderItem $item): PlaceableLine => PlaceableLineBuilder::for($item),
        )->all()));
    }

    /**
     * The admin orders list, narrowed to one status. A null filter adds no
     * clause, which is what the console's "All statuses" submits.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofStatus(Builder $query, ?OrderStatus $status): void
    {
        if ($status instanceof OrderStatus) {
            $query->where('status', $status);
        }
    }

    /**
     * The same list narrowed to one customer.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofCustomer(Builder $query, ?string $customerId): void
    {
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }
    }

    /**
     * Every order whose card cleared, the fold every money report reads
     * through: a fulfillment row exists from the moment an order is placed,
     * before a card is even charged, so a report over fulfillments alone
     * would count a cart that never paid the same as a sale.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function hasBeenPaid(Builder $query): void
    {
        $query->whereIn('status', self::paidStatuses());
    }

    /**
     * The statuses {@see hasBeenPaid} filters to — named separately so a
     * query built inside a `whereHas` closure can reach it as a plain
     * `whereIn`, where Larastan does not resolve a custom scope call.
     *
     * @return list<OrderStatus>
     */
    public static function paidStatuses(): array
    {
        return array_values(array_filter(
            OrderStatus::cases(),
            fn (OrderStatus $status): bool => $status->hasBeenPaid(),
        ));
    }

    /**
     * One row per status the table holds, carrying how many hold it — the
     * dashboard's order tally reads this the way `Listing::countedByStatus`
     * feeds the listing one.
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
     * The same tally, for `/admin`'s order count.
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

    public function subtotal(): Money
    {
        return Money::fromCents($this->subtotal_cents);
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents);
    }

    public function refunded(): Money
    {
        return Money::fromCents($this->refunded_cents);
    }

    /**
     * Nothing has been charged yet, so ending the order is still cancelling
     * rather than refunding. It is what the customer's and the admin's cancel
     * controls are shown by.
     */
    public function isCancellable(): bool
    {
        return $this->status->canTransitionTo(OrderStatus::Cancelled);
    }
}
