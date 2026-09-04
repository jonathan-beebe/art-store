<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\StoreImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One picture a store keeps. The profile points at two of them by column
 * (the portrait and the cover); a gallery section places the rest through
 * {@see StoreSectionImage}, so one picture can appear in more than one
 * gallery.
 */
#[Fillable(['store_profile_id', 'seller_id', 'path', 'alt'])]
class StoreImage extends Model
{
    /** @use HasFactory<StoreImageFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'sim';
    }

    /** @return BelongsTo<StoreProfile, $this> */
    public function storeProfile(): BelongsTo
    {
        return $this->belongsTo(StoreProfile::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return HasMany<StoreSectionImage, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(StoreSectionImage::class);
    }

    /**
     * A relative path, so it is always same-origin under the CSP's
     * `img-src 'self'` regardless of which host the app is browsed at.
     */
    public function url(): string
    {
        return '/storage/'.$this->path;
    }
}
