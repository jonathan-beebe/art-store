<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\PricedOption;
use App\Domain\Money\Money;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\OptionValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One choice on an {@see OptionAxis} (Gold, Silver, …), with the price delta
 * choosing it adds.
 */
#[Fillable(['axis_id', 'seller_id', 'property_value_id', 'label', 'surcharge_cents', 'price_cents', 'is_default', 'position'])]
class OptionValue extends Model
{
    /** @use HasFactory<OptionValueFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'ovl';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'surcharge_cents' => 'integer',
            'price_cents' => 'integer',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<OptionAxis, $this> */
    public function axis(): BelongsTo
    {
        return $this->belongsTo(OptionAxis::class, 'axis_id');
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<PropertyValue, $this> */
    public function propertyValue(): BelongsTo
    {
        return $this->belongsTo(PropertyValue::class);
    }

    /** @return HasMany<VariantOption, $this> */
    public function variantOptions(): HasMany
    {
        return $this->hasMany(VariantOption::class, 'option_value_id');
    }

    /** @return HasMany<ModifierScope, $this> */
    public function modifierScopes(): HasMany
    {
        return $this->hasMany(ModifierScope::class, 'option_value_id');
    }

    public function surcharge(): Money
    {
        return Money::fromCents($this->surcharge_cents);
    }

    /**
     * This option's own absolute price — meaningful only on a `standalone`
     * axis; a `null` row (every `add_on` axis's option) reads as zero rather
     * than forcing every caller to null-check.
     */
    public function price(): Money
    {
        return Money::fromCents($this->price_cents ?? 0);
    }

    /**
     * This value as the pricer reads it, under the axis that prices it.
     */
    public function toPriced(OptionAxis $axis): PricedOption
    {
        return PricedOption::of($this->id, $axis->name, $this->label, $axis->pricing_mode->isStandalone(), $this->price(), $this->surcharge());
    }
}
