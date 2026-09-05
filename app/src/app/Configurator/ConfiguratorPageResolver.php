<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\ComboKey;
use App\Domain\Configurator\OptionAvailability;
use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\QuantityDiscount;
use App\Domain\Money\Money;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the {@see ListingConfiguration} one render of `/art/{slug}` (or one
 * add-to-cart POST) needs, folding a listing's axes, variants, units,
 * modifiers, and quantity breaks against the buyer's raw choices — the
 * adapter {@see \App\Domain\Configurator} core rules read, so a controller
 * carries no domain `if`s of its own.
 */
final class ConfiguratorPageResolver
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Whether a listing has anything to configure at all — the legacy,
     * zero-axis listing with no variant, no modifier, and no quantity
     * discount keeps its one-click add and never reaches this resolver. A
     * quantity discount alone still earns the configurator, since its tier
     * table and its live-priced total only exist there.
     */
    public static function hasConfigurator(Listing $listing): bool
    {
        return $listing->optionAxes()->exists() || $listing->variants()->exists() || $listing->modifiers()->exists() || $listing->quantityBreaks()->exists();
    }

    public static function resolve(Listing $listing, ConfiguratorInput $input): ListingConfiguration
    {
        $axisModels = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();
        [$selected, $selectedOptionValues, $optionValueById] = SelectedAxisValues::resolve($axisModels, $input->axisSelections);
        $axisById = $axisModels->keyBy('id');

        $variantModels = $listing->variants()->with(['options.optionValue', 'units'])->get();
        [$enabledByComboKey, $availableByComboKey, $variantByComboKey] = self::comboKeyMaps($variantModels);

        $comboKey = ComboKey::of(array_values($selected))->value;
        $matchedVariant = $variantByComboKey[$comboKey] ?? null;

        $axesPresentation = self::buildAxesPresentation($axisModels, $selected, $enabledByComboKey, $availableByComboKey);

        $isSerialized = $matchedVariant !== null && $matchedVariant->is_serialized;
        [$unitsPresentation, $selectedUnitId] = SerializedUnitsPresentation::build($listing, $matchedVariant, $isSerialized, $selectedOptionValues, $input->unitId, $axisById);
        $selectedUnit = $selectedUnitId === null ? null : $matchedVariant?->units->firstWhere('id', $selectedUnitId);

        $modifierModels = $listing->modifiers()->with('options')->orderBy('position')->get();
        [$modifiersPresentation, $rawAnswers, $answersSnapshot] = ModifiersPresentation::build($modifierModels, array_values($selected), $input->modifierAnswers);

        $breakModels = $listing->quantityBreaks()->orderBy('min_qty')->get();
        $quantityTiers = self::buildQuantityTiers($breakModels, $input->quantity);

        $breakdown = ConfigurationPricer::price($listing, $selectedOptionValues, $matchedVariant, $selectedUnit, $rawAnswers, $input->quantity);

        $overallAvailability = OptionAvailability::resolve($comboKey, $enabledByComboKey, $availableByComboKey);
        $canAddToCart = $overallAvailability->selectable && (! $isSerialized || $selectedUnitId !== null);

        $configurationSnapshot = self::buildConfigurationSnapshot($axisModels, $selected, $optionValueById);

        return new ListingConfiguration(
            hasConfigurator: $axisModels->isNotEmpty() || $variantModels->isNotEmpty() || $modifierModels->isNotEmpty() || $breakModels->isNotEmpty(),
            hasVariants: $variantModels->isNotEmpty(),
            axes: $axesPresentation,
            selectedOptionValueIdsByAxis: $selected,
            variantId: $matchedVariant?->id,
            isSerialized: $isSerialized,
            units: $unitsPresentation,
            selectedUnitId: $selectedUnitId,
            modifiers: $modifiersPresentation,
            quantity: $input->quantity,
            quantityTiers: $quantityTiers,
            breakdown: $breakdown,
            canAddToCart: $canAddToCart,
            unavailableReason: $overallAvailability->reason,
            configurationSnapshot: $configurationSnapshot,
            answersSnapshot: $answersSnapshot,
            fingerprintAnswers: $rawAnswers,
        );
    }

    /**
     * Each variant's enablement, stock availability, and row, keyed by its
     * combo key, so a candidate combination can be judged by
     * {@see OptionAvailability} in one lookup.
     *
     * @param  Collection<int, Variant>  $variantModels
     * @return array{0: array<string, bool>, 1: array<string, bool>, 2: array<string, Variant>}
     */
    private static function comboKeyMaps(Collection $variantModels): array
    {
        $enabledByComboKey = [];
        $availableByComboKey = [];
        $variantByComboKey = [];

        foreach ($variantModels as $variant) {
            $enabledByComboKey[$variant->combo_key] = $variant->enabled;
            $availableByComboKey[$variant->combo_key] = $variant->availability()->available;
            $variantByComboKey[$variant->combo_key] = $variant;
        }

        return [$enabledByComboKey, $availableByComboKey, $variantByComboKey];
    }

    /**
     * @param  Collection<int, QuantityBreak>  $breakModels
     * @return list<array{minQty: int, discountPercent: float, active: bool}>
     */
    private static function buildQuantityTiers(Collection $breakModels, int $quantity): array
    {
        $tiers = array_values($breakModels->map(fn (QuantityBreak $break): QuantityDiscount => $break->toDomain())->all());
        $bestTier = QuantityDiscount::bestFor($tiers, $quantity);

        $quantityTiers = [];

        foreach ($breakModels as $break) {
            $quantityTiers[] = [
                'minQty' => $break->min_qty,
                'discountPercent' => $break->discount_bps / 100.0,
                'active' => $bestTier !== null && $bestTier->minQty === $break->min_qty,
            ];
        }

        return $quantityTiers;
    }

    /**
     * @param  Collection<int, OptionAxis>  $axisModels
     * @param  array<string, string>  $selected
     * @param  array<string, OptionValue>  $optionValueById
     * @return list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>
     */
    private static function buildConfigurationSnapshot(Collection $axisModels, array $selected, array $optionValueById): array
    {
        $configurationSnapshot = [];

        foreach ($axisModels as $axis) {
            // An axis with no option values yet has no entry in $selected
            // (skipped when the defaults were built) and so nothing to
            // snapshot for it.
            if (! array_key_exists($axis->id, $selected)) {
                continue;
            }

            $value = $optionValueById[$selected[$axis->id]];
            $configurationSnapshot[] = [
                'axisId' => $axis->id,
                'axisName' => $axis->name,
                'optionValueId' => $value->id,
                'optionValueLabel' => $value->label,
            ];
        }

        return $configurationSnapshot;
    }

    /**
     * @param  Collection<int, OptionAxis>  $axisModels
     * @param  array<string, string>  $selected
     * @param  array<string, bool>  $enabledByComboKey
     * @param  array<string, bool>  $availableByComboKey
     * @return list<array{id: string, name: string, pricingMode: PricingMode, options: list<array{id: string, label: string, delta: Money, price: Money, selected: bool, selectable: bool, reason: ?string}>}>
     */
    private static function buildAxesPresentation(Collection $axisModels, array $selected, array $enabledByComboKey, array $availableByComboKey): array
    {
        $presentation = [];

        foreach ($axisModels as $axis) {
            // An axis with no option values yet has nothing to offer and no
            // entry in $selected — the same gap the defaults build skipped.
            if ($axis->optionValues->isEmpty()) {
                continue;
            }

            $otherSelections = array_values(array_filter($selected, fn (string $axisId): bool => $axisId !== $axis->id, ARRAY_FILTER_USE_KEY));

            $options = [];

            foreach ($axis->optionValues->sortBy('position') as $value) {
                $candidateComboKey = ComboKey::of([...$otherSelections, $value->id])->value;
                $availability = OptionAvailability::resolve($candidateComboKey, $enabledByComboKey, $availableByComboKey);

                $options[] = [
                    'id' => $value->id,
                    'label' => $value->label,
                    'delta' => $value->surcharge(),
                    'price' => $value->price(),
                    'selected' => $selected[$axis->id] === $value->id,
                    'selectable' => $availability->selectable,
                    'reason' => $availability->reason,
                ];
            }

            $presentation[] = ['id' => $axis->id, 'name' => $axis->name, 'pricingMode' => $axis->pricing_mode, 'options' => $options];
        }

        return $presentation;
    }
}
