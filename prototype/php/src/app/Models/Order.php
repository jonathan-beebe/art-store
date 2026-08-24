<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Orders\OrderPlacementPlan;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\PlaceableLine;
use App\Domain\Payments\PaymentStatus;
use App\Models\Concerns\HasPrefixedUlid;
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

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
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
        return $this->payments()->one()->where('status', PaymentStatus::Approved)->latestOfMany('processed_at');
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
            fn (OrderItem $item): PlaceableLine => new PlaceableLine(
                listingId: $item->listing_id,
                title: $item->title,
                status: $item->listing->status,
                availableQuantity: $item->listing->quantity,
                quantity: $item->quantity,
                // FEAT-024 wires an admin listing removal in here.
                hasActiveRemoval: false,
            ),
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
