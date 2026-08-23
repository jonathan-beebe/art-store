<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
