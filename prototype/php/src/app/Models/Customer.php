<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;

#[Fillable(['email', 'name', 'email_verified_at'])]
#[Hidden(['remember_token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Cart, $this> */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** @return BelongsToMany<Listing, $this> */
    public function favoriteListings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites');
    }

    /** @return HasMany<ListingEvent, $this> */
    public function listingEvents(): HasMany
    {
        return $this->hasMany(ListingEvent::class);
    }

    /**
     * A merge hands the verified customer whatever cart the anonymous visitor
     * was filling, so they can own two. The one holding items is the one the
     * visitor was shopping with.
     */
    public function currentCart(): Cart
    {
        return $this->carts()
            ->withCount('items')
            ->orderByDesc('items_count')
            ->orderByDesc('id')
            ->first()
            ?? $this->carts()->create();
    }

    /**
     * A storefront visitor gets a row before they give an address; that row is
     * theirs to claim on the day they verify one.
     */
    public function isAnonymous(): bool
    {
        return $this->email === null;
    }

    /**
     * A verified address is the one a card and an order history can hang on.
     */
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }
}
