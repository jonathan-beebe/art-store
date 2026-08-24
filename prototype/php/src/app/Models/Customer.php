<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;

#[Fillable(['email', 'name', 'email_verified_at'])]
#[Hidden(['remember_token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use HasPrefixedUlid;
    use Notifiable;

    public static function idPrefix(): string
    {
        return 'cus';
    }

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

    /** @return HasMany<CustomerBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(CustomerBlock::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return MorphMany<Message, $this> */
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }

    /** @return HasOne<CustomerBlock, $this> */
    public function activeBlock(): HasOne
    {
        return $this->blocks()->one()->whereNull('lifted_at')->latestOfMany('created_at');
    }

    /**
     * A fresh read rather than the loaded relation, so a caller that never
     * eager-loaded `activeBlock` still gets an answer under strict mode.
     */
    public function currentBlock(): ?CustomerBlock
    {
        return $this->activeBlock()->first();
    }

    /**
     * A blocked customer keeps browsing, searching, and favoriting — this is
     * the one predicate the paths that buy something and the paths that post
     * a message both read.
     */
    public function canShop(): bool
    {
        return $this->currentBlock() === null;
    }

    /**
     * The admin's own words for why this customer cannot buy right now, for
     * the refusal that names them. Null when the customer is not blocked.
     */
    public function blockReason(): ?string
    {
        return $this->currentBlock()?->reason;
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
            ->orderByDesc('created_at')
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
