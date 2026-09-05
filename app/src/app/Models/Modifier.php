<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\ModifierKind;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ModifierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One order-line question a listing asks (personalization text, a font
 * select, an engraved-length measurement). Inventory stays on the variant;
 * this attaches to the line instead.
 */
#[Fillable([
    'listing_id', 'seller_id', 'kind', 'prompt', 'instructions', 'required', 'position',
    'add_on_price_cents', 'char_limit', 'unit', 'min_value', 'max_value', 'rate_cents_per_unit',
])]
class Modifier extends Model
{
    /** @use HasFactory<ModifierFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'mdf';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => ModifierKind::class,
            'required' => 'boolean',
            'position' => 'integer',
            'add_on_price_cents' => 'integer',
            'char_limit' => 'integer',
            'min_value' => 'float',
            'max_value' => 'float',
            'rate_cents_per_unit' => 'integer',
        ];
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

    /** @return HasMany<ModifierOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class);
    }

    /** @return HasMany<ModifierScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(ModifierScope::class);
    }

    /**
     * Whether this modifier shows for a variant holding the given option
     * values. Zero scope rows means it shows product-wide; otherwise it shows
     * only when the selection includes at least one scoped value — the
     * primitive that keeps a personalization box off the blank mug.
     *
     * @param  list<string>  $selectedOptionValueIds
     */
    public function appliesTo(array $selectedOptionValueIds): bool
    {
        /** @var list<string> $scopedTo */
        $scopedTo = array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $this->scopes()->pluck('option_value_id')->all());

        return $scopedTo === [] || array_intersect($scopedTo, $selectedOptionValueIds) !== [];
    }
}
