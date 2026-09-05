<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\UnitLabelOrder;
use App\Domain\Configurator\UnitPrice;
use App\Domain\Money\Money;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Unit;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Collection;

/**
 * A serialized variant's unit picker: every unit still available, ordered
 * by `UnitLabelOrder`, the way a buyer reads labels, each priced with the
 * buyer's standalone and add-on selections folded in — and which one is
 * selected, the buyer's requested unit where it is still available, the
 * first available unit otherwise. A listing with no matched, serialized
 * variant has no units to offer at all.
 */
final class SerializedUnitsPresentation
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<OptionValue>  $selectedOptionValues
     * @param  Collection<string, OptionAxis>  $axisById
     * @return array{0: list<array{id: string, label: string, conditionNote: ?string, specLines: list<string>, price: Money, selected: bool}>, 1: ?string}
     */
    public static function build(Listing $listing, ?Variant $variant, bool $isSerialized, array $selectedOptionValues, ?string $requestedUnitId, Collection $axisById): array
    {
        if (! $isSerialized || $variant === null) {
            return [[], null];
        }

        $availableUnits = self::availableUnits($variant);
        $selectedUnitId = self::selectUnitId($availableUnits, $requestedUnitId);
        [$addonSurcharges, $standalonePrices] = self::priceInputs($selectedOptionValues, $axisById);
        $variantOverride = $variant->price_override_cents === null ? null : Money::fromCents($variant->price_override_cents);

        $presentation = [];
        foreach ($availableUnits as $unit) {
            $presentation[] = self::present($unit, $listing, $variantOverride, $addonSurcharges, $standalonePrices, $selectedUnitId);
        }

        return [$presentation, $selectedUnitId];
    }

    /**
     * @return Collection<int, Unit>
     */
    private static function availableUnits(Variant $variant): Collection
    {
        return $variant->units
            ->filter(fn (Unit $unit): bool => $unit->state->isAvailable())
            ->sort(fn (Unit $a, Unit $b): int => UnitLabelOrder::compare($a->label, $b->label))
            ->values();
    }

    /**
     * @param  Collection<int, Unit>  $availableUnits
     */
    private static function selectUnitId(Collection $availableUnits, ?string $requestedUnitId): ?string
    {
        if ($requestedUnitId !== null && $availableUnits->contains('id', $requestedUnitId)) {
            return $requestedUnitId;
        }

        return $availableUnits->first()?->id;
    }

    /**
     * The buyer's selected option values split by pricing mode: a
     * `standalone` axis prices the unit on its own, an `add_on` axis
     * surcharges whatever the unit already costs.
     *
     * @param  list<OptionValue>  $selectedOptionValues
     * @param  Collection<string, OptionAxis>  $axisById
     * @return array{0: list<Money>, 1: list<Money>}
     */
    private static function priceInputs(array $selectedOptionValues, Collection $axisById): array
    {
        $addonSurcharges = [];
        $standalonePrices = [];

        foreach ($selectedOptionValues as $value) {
            $axis = $axisById->get($value->axis_id);

            if ($axis instanceof OptionAxis && $axis->pricing_mode === PricingMode::Standalone) {
                $standalonePrices[] = $value->price();

                continue;
            }

            $addonSurcharges[] = $value->surcharge();
        }

        return [$addonSurcharges, $standalonePrices];
    }

    /**
     * @param  list<Money>  $addonSurcharges
     * @param  list<Money>  $standalonePrices
     * @return array{id: string, label: string, conditionNote: ?string, specLines: list<string>, price: Money, selected: bool}
     */
    private static function present(Unit $unit, Listing $listing, ?Money $variantOverride, array $addonSurcharges, array $standalonePrices, ?string $selectedUnitId): array
    {
        $unitOverride = $unit->price_override_cents === null ? null : Money::fromCents($unit->price_override_cents);

        return [
            'id' => $unit->id,
            'label' => $unit->label,
            'conditionNote' => $unit->condition_note,
            'specLines' => UnitSpecLines::format($unit->specs_json),
            'price' => UnitPrice::resolve($unitOverride, $variantOverride, $listing->price(), $addonSurcharges, $standalonePrices),
            'selected' => $unit->id === $selectedUnitId,
        ];
    }
}
