<?php

declare(strict_types=1);

namespace App\Models;

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
 */
#[Fillable([
    'order_id', 'seller_id', 'status', 'carrier', 'tracking_number',
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
}
