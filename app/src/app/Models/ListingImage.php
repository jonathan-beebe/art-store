<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ListingImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One photo on a listing. `position` orders the set a seller built on the
 * Images screen; the lowest position is the cover — the image every other
 * surface (shop card, cart line, seller index row) renders through
 * {@see Listing::imageUrl()}.
 */
#[Fillable(['listing_id', 'seller_id', 'path', 'position'])]
class ListingImage extends Model
{
    /**
     * The most images a listing may hold at once.
     */
    public const int MAX_PER_LISTING = 8;

    /** @use HasFactory<ListingImageFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'img';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['position' => 'integer'];
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

    /**
     * A relative path, so it is always same-origin under the CSP's
     * `img-src 'self'` regardless of which host the app is browsed at.
     */
    public function url(): string
    {
        return '/storage/'.$this->path;
    }
}
