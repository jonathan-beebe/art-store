<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Listings\ListingStock;
use App\Domain\Money\Money;
use App\Support\PlaceholderImage;
use Closure;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Override;

/**
 * @property-read Seller $seller
 */
#[Fillable([
    'seller_id', 'title', 'slug', 'description', 'price_cents',
    'quantity', 'status', 'image_path', 'medium', 'dimensions',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'price_cents' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return HasMany<ListingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ListingEvent::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents);
    }

    public function isPurchasable(): bool
    {
        return ListingAvailability::isPurchasable($this->status, $this->quantity);
    }

    /**
     * Hands the given number of items to a buyer, reaching `sold` at nothing
     * left.
     */
    public function sell(int $quantity): self
    {
        return $this->applyStock(ListingStock::afterSale($this->quantity, $this->status, $quantity, $this->title));
    }

    /**
     * Puts items a sale took back on the shelf, and a sold-out listing back on
     * the storefront.
     */
    public function restock(int $quantity): self
    {
        return $this->applyStock(ListingStock::afterRestock($this->quantity, $this->status, $quantity));
    }

    public function changeStatusTo(ListingStatus $next): self
    {
        $this->update(['status' => $this->status->transitionTo($next)]);

        return $this;
    }

    /**
     * The one place quantity and status are written together, so the pair the
     * core decided on is the pair the row holds.
     */
    private function applyStock(ListingStock $stock): self
    {
        $this->update(['quantity' => $stock->quantity, 'status' => $stock->status]);

        return $this;
    }

    public function imageUrl(): string
    {
        return $this->image_path === null
            ? PlaceholderImage::dataUri($this->title)
            : Storage::disk('public')->url($this->image_path);
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function forSale(Builder $query): void
    {
        $query->where('status', ListingStatus::ForSale);
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function withEventCounts(Builder $query): void
    {
        $query->withCount(self::eventCounts());
    }

    /**
     * The same three counts the `withEventCounts` scope selects, for a listing
     * already in hand — a route-bound model, say.
     */
    public function loadEventCounts(): self
    {
        $this->loadCount(self::eventCounts());

        return $this;
    }

    /**
     * @return array<string, Closure(Builder<ListingEvent>): Builder<ListingEvent>>
     */
    private static function eventCounts(): array
    {
        return [
            'events as views_count' => fn (Builder $events) => $events->where('type', ListingEventType::View),
            'events as favorites_count' => fn (Builder $events) => $events->where('type', ListingEventType::Favorite),
            'events as cart_adds_count' => fn (Builder $events) => $events->where('type', ListingEventType::CartAdd),
        ];
    }
}
