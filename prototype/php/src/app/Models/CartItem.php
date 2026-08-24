<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cart\CartLine;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property-read Cart $cart
 * @property-read Listing $listing
 */
#[Fillable(['cart_id', 'listing_id', 'quantity'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'cti';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function toLine(): CartLine
    {
        return CartLine::of($this->listing->seller_id, $this->listing->price(), $this->quantity);
    }
}
