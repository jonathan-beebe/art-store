<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\UnitState;
use App\Domain\Configurator\VariantAvailability;
use App\Domain\Configurator\VariantPrice;
use App\Domain\Configurator\VariantStock;
use App\Domain\Money\Money;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\VariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Override;

/**
 * One sellable combination of a listing's option values — a sparse row, so a
 * seller creates only the combinations that actually sell rather than every
 * cell of the full cross product.
 */
#[Fillable(['listing_id', 'combo_key', 'sku', 'price_override_cents', 'quantity', 'is_serialized', 'enabled'])]
class Variant extends Model
{
    /**
     * A variant at or below this available quantity reads as low on stock,
     * on both the choices summary card and the combinations table.
     */
    public const int LOW_STOCK_MAX_QUANTITY = 3;

    /** @use HasFactory<VariantFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'vrt';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'price_override_cents' => 'integer',
            'quantity' => 'integer',
            'is_serialized' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return HasMany<VariantOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(VariantOption::class);
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * The base price plus every `add_on` option value this variant holds a
     * surcharge for — or, when this variant carries a `standalone` option,
     * that option's own price in place of the base — or the flat override
     * when one is set (`docs/item-configurator.md` §3).
     */
    public function resolvedPrice(Money $basePrice): Money
    {
        $standalonePrices = [];
        $addonSurcharges = [];

        foreach ($this->options()->with('optionValue.axis')->get() as $option) {
            $value = $option->optionValue ?? throw new LogicException('A variant option always names an option value.');
            $axis = $value->axis ?? throw new LogicException('An option value always belongs to an axis.');

            if ($axis->pricing_mode === PricingMode::Standalone) {
                $standalonePrices[] = $value->price();
            } else {
                $addonSurcharges[] = $value->surcharge();
            }
        }

        return VariantPrice::resolve(
            $basePrice,
            $this->price_override_cents === null ? null : Money::fromCents($this->price_override_cents),
            $addonSurcharges,
            $standalonePrices,
        )->amount;
    }

    public function availableUnitCount(): int
    {
        return $this->units()->where('state', UnitState::Available)->count();
    }

    /**
     * This combination's name for the seller: its option labels, in choice
     * order, joined the way the buyer-view breakdown joins them. The
     * schema's empty combo key (a listing with no choices offers at most
     * one) has no options at all, so it falls back to a generic name.
     */
    public function comboLabel(): string
    {
        $labels = $this->options->map(fn (VariantOption $option): ?string => $option->optionValue?->label)->filter()->values();

        return $labels->isEmpty() ? 'This combination' : $labels->implode(' · ');
    }

    /**
     * Whether this combination is worth flagging as running low: it is
     * offered, tracked (not serialized — a piece listing's stock reads off
     * its units instead), and its remaining count is at or under the
     * threshold. An untracked (null) quantity never reads as low.
     */
    public function isLowOnStock(): bool
    {
        return $this->enabled
            && ! $this->is_serialized
            && $this->quantity !== null
            && $this->quantity <= self::LOW_STOCK_MAX_QUANTITY;
    }

    public function availability(): VariantAvailability
    {
        return VariantAvailability::resolve($this->enabled, $this->is_serialized, $this->availableUnitCount(), $this->quantity);
    }

    /**
     * @return list<string>
     */
    public function axisIdsCovered(): array
    {
        return array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', array_values($this->options()->pluck('axis_id')->all()));
    }

    /**
     * Hands the given number of items to a buyer — a non-serialized
     * configured line's stock movement, mirroring {@see Listing::sell()}. An
     * untracked (null) quantity stays untracked.
     */
    public function decrementQuantity(int $by): self
    {
        $this->update(['quantity' => VariantStock::afterSale($this->quantity, $by)]);

        return $this;
    }

    /**
     * Puts items a sale took back, mirroring {@see Listing::restock()}.
     */
    public function restoreQuantity(int $by): self
    {
        $this->update(['quantity' => VariantStock::afterRestock($this->quantity, $by)]);

        return $this;
    }

    /**
     * Takes the rows placement reads for update, in id order — the same
     * discipline {@see Listing::lockedForPlacement()} holds a listing row to,
     * so a configured line's variant is held from the read that judges it to
     * the write that claims it.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function lockedForPlacement(Builder $query): void
    {
        $query->orderBy('id')->lockForUpdate();
    }
}
