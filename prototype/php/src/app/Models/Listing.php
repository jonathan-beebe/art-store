<?php

namespace App\Models;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Support\PlaceholderImage;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'price_cents' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ListingEvent::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents);
    }

    public function imageUrl(): string
    {
        return $this->image_path === null
            ? PlaceholderImage::dataUri($this->title)
            : Storage::disk('public')->url($this->image_path);
    }

    #[Scope]
    protected function forSale(Builder $query): void
    {
        $query->where('status', ListingStatus::ForSale);
    }

    #[Scope]
    protected function withEventCounts(Builder $query): void
    {
        $query->withCount([
            'events as views_count' => fn (Builder $events) => $events->where('type', ListingEventType::View),
            'events as favorites_count' => fn (Builder $events) => $events->where('type', ListingEventType::Favorite),
            'events as cart_adds_count' => fn (Builder $events) => $events->where('type', ListingEventType::CartAdd),
        ]);
    }
}
