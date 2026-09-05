<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\CategoryPropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One grant: a {@see Category} allows a {@see Property} to be used on the
 * listings placed in it, and how — as a search attribute, a buyer-facing
 * option axis, required, or repeatable per listing.
 *
 * @property-read Category $category
 * @property-read Property $property
 */
#[Fillable(['category_id', 'property_id', 'usable_as_attribute', 'usable_as_axis', 'required', 'multivalued'])]
class CategoryProperty extends Model
{
    /** @use HasFactory<CategoryPropertyFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'cpr';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'usable_as_attribute' => 'boolean',
            'usable_as_axis' => 'boolean',
            'required' => 'boolean',
            'multivalued' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
