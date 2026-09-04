<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Store\StoreVisibility;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\StoreProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * How one seller presents on the site: the identity every store has once —
 * name, address, pictures, visibility. Everything the page says is a
 * {@see StoreSection} row of a typed kind, so a new kind of store content
 * is a case and a renderer rather than a wider row here.
 *
 * `slug` is the address the store answers to today; {@see StoreSlug} holds
 * every address it has ever answered to.
 */
#[Fillable(['seller_id', 'slug', 'name', 'tagline', 'location', 'portrait_image_id', 'cover_image_id', 'published_at'])]
class StoreProfile extends Model
{
    /**
     * The most characters a tagline holds — one line under the name.
     */
    public const int MAX_TAGLINE_LENGTH = 80;

    /**
     * The most pictures a store keeps at once, across its portrait, its
     * cover, and every gallery.
     */
    public const int MAX_IMAGES = 24;

    /** @use HasFactory<StoreProfileFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'sto';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return HasMany<StoreSlug, $this> */
    public function slugs(): HasMany
    {
        return $this->hasMany(StoreSlug::class);
    }

    /** @return HasMany<StoreImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(StoreImage::class);
    }

    /** @return HasMany<StoreSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(StoreSection::class)->orderBy('position');
    }

    /** @return HasMany<StoreLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(StoreLink::class)->orderBy('position');
    }

    /** @return BelongsTo<StoreImage, $this> */
    public function portraitImage(): BelongsTo
    {
        return $this->belongsTo(StoreImage::class, 'portrait_image_id');
    }

    /** @return BelongsTo<StoreImage, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(StoreImage::class, 'cover_image_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function visibility(): StoreVisibility
    {
        return $this->isPublished() ? StoreVisibility::Published : StoreVisibility::Hidden;
    }

    /**
     * The stores a buyer can open, the only ones `/s/{slug}` answers.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }
}
