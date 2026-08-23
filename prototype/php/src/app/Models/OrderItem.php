<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property-read Order $order
 * @property-read Listing $listing
 * @property-read Seller $seller
 */
#[Fillable(['order_id', 'listing_id', 'seller_id', 'title', 'unit_price_cents', 'quantity'])]
class OrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function unitPrice(): Money
    {
        return Money::fromCents($this->unit_price_cents);
    }

    public function lineTotal(): Money
    {
        return $this->unitPrice()->multiply($this->quantity);
    }
}
