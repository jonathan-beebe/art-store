<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\PropertyDataType;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One catalog property (Metal, Ring Size, Paper Stock, …) a category may
 * grant to its listings, as an attribute, an option axis, or both — see
 * {@see CategoryProperty}.
 */
#[Fillable(['name', 'data_type'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'prp';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['data_type' => PropertyDataType::class];
    }

    /** @return HasMany<PropertyValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(PropertyValue::class);
    }

    /** @return HasMany<CategoryProperty, $this> */
    public function categoryProperties(): HasMany
    {
        return $this->hasMany(CategoryProperty::class);
    }
}
