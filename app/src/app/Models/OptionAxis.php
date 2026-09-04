<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\PricingMode;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\OptionAxisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One buyer-facing choice a listing offers (Metal, Size, …), either a catalog
 * property or a custom, label-only axis when `property_id` is null.
 */
#[Fillable(['listing_id', 'seller_id', 'property_id', 'name', 'position', 'pricing_mode'])]
class OptionAxis extends Model
{
    /** @use HasFactory<OptionAxisFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'axs';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'pricing_mode' => PricingMode::class,
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

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasMany<OptionValue, $this> */
    public function optionValues(): HasMany
    {
        return $this->hasMany(OptionValue::class, 'axis_id');
    }
}
