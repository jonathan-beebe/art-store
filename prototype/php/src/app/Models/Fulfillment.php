<?php

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'seller_id', 'status', 'carrier', 'tracking_number',
    'shipped_at', 'delivered_at', 'subtotal_cents', 'fee_cents', 'net_cents',
])]
class Fulfillment extends Model
{
    /**
     * @return array<string, string>
     */
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function net(): Money
    {
        return Money::fromCents($this->net_cents);
    }
}
