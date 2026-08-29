<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\DescriptionSectionKind;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\DescriptionSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One typed slice of a listing's description (specs, a size chart, care
 * instructions, …), so "How to Order" and "What's Included" can render from
 * data rather than pasted prose.
 *
 * @property-read array<int|string, mixed>|null $body_json
 */
#[Fillable(['listing_id', 'position', 'kind', 'title', 'body_md', 'body_json'])]
class DescriptionSection extends Model
{
    /** @use HasFactory<DescriptionSectionFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'dsc';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['position' => 'integer', 'kind' => DescriptionSectionKind::class, 'body_json' => 'array'];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
