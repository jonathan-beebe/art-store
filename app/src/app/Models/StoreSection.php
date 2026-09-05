<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Store\StoreSectionKind;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\StoreSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One block of a store page, ordered by `position` inside its profile. The
 * kind decides which columns carry meaning:
 * {@see StoreSectionKind::allows()} is the statement of that, and the form
 * request refuses a field the kind does not use.
 */
#[Fillable(['store_profile_id', 'kind', 'position', 'heading', 'body'])]
class StoreSection extends Model
{
    /**
     * The most characters a story body holds.
     */
    public const int MAX_BODY_LENGTH = 4000;

    /**
     * The most pictures one gallery section places.
     */
    public const int MAX_GALLERY_IMAGES = 8;

    /**
     * The most sections one store page is built from.
     */
    public const int MAX_PER_PROFILE = 12;

    /** @use HasFactory<StoreSectionFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'sse';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => StoreSectionKind::class,
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<StoreProfile, $this> */
    public function storeProfile(): BelongsTo
    {
        return $this->belongsTo(StoreProfile::class);
    }

    /** @return HasMany<StoreSectionImage, $this> */
    public function sectionImages(): HasMany
    {
        return $this->hasMany(StoreSectionImage::class)->orderBy('position');
    }
}
