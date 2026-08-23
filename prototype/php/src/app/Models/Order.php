<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use App\Domain\Orders\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read Customer $customer
 */
#[Fillable([
    'customer_id', 'email', 'status', 'shipping_name', 'shipping_line1', 'shipping_line2',
    'shipping_city', 'shipping_region', 'shipping_postal_code', 'shipping_country',
    'subtotal_cents', 'total_cents', 'placed_at', 'finalized_at',
])]
class Order extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_cents' => 'integer',
            'total_cents' => 'integer',
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

    /**
     * The attempt a page reports on: after a decline and a retry, the latest
     * one is what the shopper is looking at.
     *
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->payments()->one()->latestOfMany();
    }

    public function subtotal(): Money
    {
        return Money::fromCents($this->subtotal_cents);
    }

    public function total(): Money
    {
        return Money::fromCents($this->total_cents);
    }
}
