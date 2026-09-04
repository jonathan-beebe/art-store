<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\StoreSectionImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One of the store's pictures placed in one gallery section, at one
 * position. A picture the seller removes from a gallery loses this row and
 * stays in the store's pictures.
 */
#[Fillable(['store_section_id', 'store_image_id', 'position'])]
class StoreSectionImage extends Model
{
    /** @use HasFactory<StoreSectionImageFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'ssi';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** @return BelongsTo<StoreSection, $this> */
    public function storeSection(): BelongsTo
    {
        return $this->belongsTo(StoreSection::class);
    }

    /** @return BelongsTo<StoreImage, $this> */
    public function storeImage(): BelongsTo
    {
        return $this->belongsTo(StoreImage::class);
    }
}
