<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cart\CartLine;
use App\Domain\Orders\OrderPlacementPlan;
use App\Domain\Orders\PlaceableLine;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Customer $customer
 */
#[Fillable(['customer_id'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'crt';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return list<CartLine>
     */
    public function lines(): array
    {
        return array_values($this->items->map(fn (CartItem $item): CartLine => $item->toLine())->all());
    }

    /**
     * How placement judges this cart's lines against the listings behind
     * them, right now — the cart page's answer for marking a blocked line
     * and disabling checkout. `PlaceOrder` builds the same plan again inside
     * its own transaction, from rows it holds for update, because what this
     * reads is already stale by the time a shopper acts on it.
     */
    public function placementPlan(): OrderPlacementPlan
    {
        return OrderPlacementPlan::for(array_values($this->items->map(
            fn (CartItem $item): PlaceableLine => new PlaceableLine(
                listingId: $item->listing_id,
                title: $item->listing->title,
                status: $item->listing->status,
                availableQuantity: $item->listing->quantity,
                quantity: $item->quantity,
                hasActiveRemoval: $item->listing->hasActiveRemoval(),
            ),
        )->all()));
    }
}
