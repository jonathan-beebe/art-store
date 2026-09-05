<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Customers\StandingFilter;
use App\Domain\Messaging\ParticipantName;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;
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

    /**
     * Every line in every cart the customer holds, so a page counts or lists
     * them without walking the carts first.
     *
     * @return HasManyThrough<CartItem, Cart, $this>
     */
    public function cartItems(): HasManyThrough
    {
        return $this->hasManyThrough(CartItem::class, Cart::class);
    }

    /**
     * Merges this customer absorbed: an anonymous visitor's rows folded into
     * this account when they verified an address.
     *
     * @return HasMany<CustomerMerge, $this>
     */
    public function mergesAsCustomer(): HasMany
    {
        return $this->hasMany(CustomerMerge::class);
    }

    /**
     * The merge that folded this row into someone else, which only an
     * anonymous customer has.
     *
     * @return HasMany<CustomerMerge, $this>
     */
    public function mergesAsAnonymous(): HasMany
    {
        return $this->hasMany(CustomerMerge::class, 'anonymous_customer_id');
    }

    /** @return BelongsToMany<Listing, $this> */
    public function favoriteListings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites');
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
     * Queries `activeBlock` fresh on every call, so a caller that never
     * eager-loaded it still gets an answer under strict mode.
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
     * A customer holds at most one cart. `MergeAnonymousCustomer` merges an
     * anonymous visitor's cart into the verified customer's own and retires
     * the anonymous cart, so there is never a second one to choose between.
     *
     * An unsaved row has no id a cart could be keyed by, so this refuses
     * rather than minting one for a visitor nothing has committed yet — a
     * caller reaching this on an unsaved row is the bug, not the row.
     * `cartOrNull()` is how a read tolerates one.
     */
    public function cart(): Cart
    {
        if (! $this->exists) {
            throw new LogicException('An unsaved customer has no cart to hold.');
        }

        return $this->carts()->first() ?? $this->carts()->create();
    }

    /**
     * The cart page's read: the customer's cart when one exists, and no
     * query at all — instead of a create — for a visitor who has never
     * added anything, saved or not.
     */
    public function cartOrNull(): ?Cart
    {
        return $this->exists ? $this->carts()->first() : null;
    }

    /**
     * A storefront visitor's row, once one exists, holds no address until they
     * give one; that row is theirs to claim on the day they verify one.
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

    /**
     * What the admin console calls this customer: their name, the address
     * they verified, or — for a visitor who has given neither — their id.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->email ?? $this->id;
    }

    /**
     * Everything the admin console's customer page reads off this row. Two
     * routes render that page — the page itself, and the message form on it
     * handing the page back when the message-post budget is spent — so the
     * eager loads it needs are named here once.
     */
    public function loadForConsole(): static
    {
        return $this->load([
            'activeBlock',
            'orders' => fn (Relation $orders) => $orders->withCount('items')->orderByDesc('placed_at')->orderByDesc('id'),
            'blocks' => fn (Relation $blocks) => $blocks->orderByDesc('created_at')->orderByDesc('id'),
            'favorites.listing',
            'cartItems.listing',
            'mergesAsCustomer',
            'mergesAsAnonymous',
        ]);
    }

    /**
     * Takes the rows a moderation decision is judged against for update. A
     * block is refused when one already stands, and that check queries the
     * `customer_blocks` table alone; locking this row does not guard it, so
     * both admins can read no active block and both insert one. The
     * customer row they each have to take first is what serialises them.
     * SQLite, which the app develops and tests on, serialises writers with a
     * database-level lock; its grammar compiles the clause away.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function lockedForModeration(Builder $query): void
    {
        $query->lockForUpdate();
    }

    /**
     * Re-reads this row for update inside the caller's transaction, the way
     * `Fulfillment::takeForTransition` re-reads the row a transition is
     * judged against.
     */
    public function takeForModeration(): static
    {
        /** @var static $locked */
        $locked = $this->newQuery()->whereKey($this->getKey())->lockedForModeration()->sole();

        return $this->setRawAttributes($locked->getAttributes(), sync: true);
    }

    /**
     * The admin customers list, narrowed to one standing. `All` adds no
     * clause at all, which is what an empty filter asks for.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function inStanding(Builder $query, StandingFilter $standing): void
    {
        match ($standing) {
            StandingFilter::All => $query,
            StandingFilter::Verified => $query->whereNotNull('email_verified_at'),
            StandingFilter::Anonymous => $query->whereNull('email'),
            StandingFilter::Blocked => $query->whereHas('blocks', fn (Builder $blocks): Builder => $blocks->whereNull('lifted_at')),
        };
    }

    /**
     * How the other side of a thread names this customer: their given name,
     * or their id. The address they verified stays with the admin console's
     * `displayName()`.
     */
    public function participantName(): string
    {
        return ParticipantName::forCustomer($this->name, $this->id);
    }
}
