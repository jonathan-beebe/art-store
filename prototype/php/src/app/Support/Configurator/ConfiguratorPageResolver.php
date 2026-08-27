<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\AxisDefaults;
use App\Domain\Configurator\AxisSelectionResolver;
use App\Domain\Configurator\ComboKey;
use App\Domain\Configurator\ModifierAnswerPrice;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\OptionAvailability;
use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\QuantityDiscount;
use App\Domain\Configurator\UnitLabelOrder;
use App\Domain\Configurator\UnitPrice;
use App\Domain\Money\Money;
use App\Models\Listing;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\Unit;
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

        $axisDefaults = [];
        $optionValueById = [];

        foreach ($axisModels as $axis) {
            $values = $axis->optionValues->sortBy('position')->values();
            $defaultValue = $values->firstWhere('is_default', true) ?? $values->first();

            if ($defaultValue === null) {
                continue;
            }

            foreach ($values as $value) {
                $optionValueById[$value->id] = $value;
            }

            $axisDefaults[] = AxisDefaults::of($axis->id, array_values($values->map(fn (OptionValue $value): string => $value->id)->all()), $defaultValue->id);
        }

        $selected = AxisSelectionResolver::resolve($axisDefaults, $input->axisSelections);
        $selectedOptionValues = array_values(array_map(fn (string $id): OptionValue => $optionValueById[$id], $selected));
        $axisById = $axisModels->keyBy('id');

        $variantModels = $listing->variants()->with(['options.optionValue', 'units'])->get();

        $enabledByComboKey = [];
        $availableByComboKey = [];
        $variantByComboKey = [];

        foreach ($variantModels as $variant) {
            $enabledByComboKey[$variant->combo_key] = $variant->enabled;
            $availableByComboKey[$variant->combo_key] = $variant->availability()->available;
            $variantByComboKey[$variant->combo_key] = $variant;
        }

        $comboKey = ComboKey::of(array_values($selected))->value;
        $matchedVariant = $variantByComboKey[$comboKey] ?? null;

        $axesPresentation = self::buildAxesPresentation($axisModels, $selected, $enabledByComboKey, $availableByComboKey);

        $isSerialized = $matchedVariant !== null && $matchedVariant->is_serialized;
        [$unitsPresentation, $selectedUnitId] = self::buildUnitsPresentation($listing, $matchedVariant, $isSerialized, $selectedOptionValues, $input->unitId, $axisById);
        $selectedUnit = $selectedUnitId === null ? null : $matchedVariant?->units->firstWhere('id', $selectedUnitId);

        $modifierModels = $listing->modifiers()->with('options')->orderBy('position')->get();
        $selectedIds = array_values($selected);

        $modifiersPresentation = [];
        $rawAnswers = [];
        $answersSnapshot = [];

        foreach ($modifierModels as $modifier) {
            if (! $modifier->appliesTo($selectedIds)) {
                continue;
            }

            $resolvedAnswer = self::resolveModifierAnswer($modifier, $input->modifierAnswers[$modifier->id] ?? null);

            $modifiersPresentation[] = [
                'id' => $modifier->id,
                'prompt' => $modifier->prompt,
                'instructions' => $modifier->instructions,
                'kind' => $modifier->kind,
                'required' => $modifier->required,
                'charLimit' => $modifier->char_limit,
                'unit' => $modifier->unit,
                'minValue' => $modifier->min_value,
                'maxValue' => $modifier->max_value,
                'addOnPriceCents' => $modifier->add_on_price_cents,
                'options' => $resolvedAnswer['options'],
                'answer' => $resolvedAnswer['resolvedAnswer'],
                'delta' => $resolvedAnswer['delta'],
            ];

            if ($resolvedAnswer['resolvedAnswer'] !== '') {
                $rawAnswers[$modifier->id] = $resolvedAnswer['resolvedAnswer'];
                $answersSnapshot[$modifier->id] = [
                    'prompt' => $modifier->prompt,
                    'answer' => $resolvedAnswer['displayAnswer'],
                    'raw' => $resolvedAnswer['resolvedAnswer'],
                ];
            }
        }

        $breakModels = $listing->quantityBreaks()->orderBy('min_qty')->get();
        $tiers = array_values($breakModels->map(fn (QuantityBreak $break): QuantityDiscount => $break->toDomain())->all());
        $bestTier = QuantityDiscount::bestFor($tiers, $input->quantity);

        $quantityTiers = [];
        foreach ($breakModels as $break) {
            $quantityTiers[] = [
                'minQty' => $break->min_qty,
                'discountPercent' => $break->discount_bps / 100.0,
                'active' => $bestTier !== null && $bestTier->minQty === $break->min_qty,
            ];
        }

        $breakdown = ConfigurationPricer::price($listing, $selectedOptionValues, $matchedVariant, $selectedUnit, $rawAnswers, $input->quantity);

        $overallAvailability = OptionAvailability::resolve($comboKey, $enabledByComboKey, $availableByComboKey);
        $canAddToCart = $overallAvailability->selectable && (! $isSerialized || $selectedUnitId !== null);

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

    /**
     * @param  list<OptionValue>  $selectedOptionValues
     * @param  Collection<string, OptionAxis>  $axisById
     * @return array{0: list<array{id: string, label: string, conditionNote: ?string, specLines: list<string>, price: Money, selected: bool}>, 1: ?string}
     */
    private static function buildUnitsPresentation(Listing $listing, ?Variant $variant, bool $isSerialized, array $selectedOptionValues, ?string $requestedUnitId, Collection $axisById): array
    {
        if (! $isSerialized || $variant === null) {
            return [[], null];
        }

        $availableUnits = $variant->units
            ->filter(fn (Unit $unit): bool => $unit->state->isAvailable())
            ->sort(fn (Unit $a, Unit $b): int => UnitLabelOrder::compare($a->label, $b->label))
            ->values();

        $selectedUnitId = $requestedUnitId !== null && $availableUnits->contains('id', $requestedUnitId)
            ? $requestedUnitId
            : $availableUnits->first()?->id;

        $addonSurcharges = [];
        $standalonePrices = [];

        foreach ($selectedOptionValues as $value) {
            $axis = $axisById->get($value->axis_id);
            $isStandalone = $axis instanceof OptionAxis && $axis->pricing_mode === PricingMode::Standalone;

            if ($isStandalone) {
                $standalonePrices[] = $value->price();
            } else {
                $addonSurcharges[] = $value->surcharge();
            }
        }

        $variantOverride = $variant->price_override_cents === null ? null : Money::fromCents($variant->price_override_cents);

        $presentation = [];

        foreach ($availableUnits as $unit) {
            $unitOverride = $unit->price_override_cents === null ? null : Money::fromCents($unit->price_override_cents);

            $presentation[] = [
                'id' => $unit->id,
                'label' => $unit->label,
                'conditionNote' => $unit->condition_note,
                'specLines' => UnitSpecLines::format($unit->specs_json),
                'price' => UnitPrice::resolve($unitOverride, $variantOverride, $listing->price(), $addonSurcharges, $standalonePrices),
                'selected' => $unit->id === $selectedUnitId,
            ];
        }

        return [$presentation, $selectedUnitId];
    }

    /**
     * @return array{resolvedAnswer: string, delta: Money, displayAnswer: string, options: list<array{id: string, label: string, delta: Money, selected: bool}>}
     */
    private static function resolveModifierAnswer(Modifier $modifier, ?string $rawAnswer): array
    {
        return match ($modifier->kind) {
            ModifierKind::Select => self::resolveSelectAnswer($modifier, $rawAnswer),
            ModifierKind::Text => self::resolveTextAnswer($modifier, $rawAnswer),
            ModifierKind::Measurement => self::resolveMeasurementAnswer($modifier, $rawAnswer),
        };
    }

    /**
     * @return array{resolvedAnswer: string, delta: Money, displayAnswer: string, options: list<array{id: string, label: string, delta: Money, selected: bool}>}
     */
    private static function resolveSelectAnswer(Modifier $modifier, ?string $rawAnswer): array
    {
        $sortedOptions = $modifier->options->sortBy('position')->values();
        $firstOption = $sortedOptions->first();

        $resolvedAnswer = $rawAnswer !== null && $sortedOptions->contains('id', $rawAnswer)
            ? $rawAnswer
            : ($firstOption instanceof ModifierOption ? $firstOption->id : '');

        $options = [];
        foreach ($sortedOptions as $option) {
            $options[] = [
                'id' => $option->id,
                'label' => $option->label,
                'delta' => $option->addOn(),
                'selected' => $option->id === $resolvedAnswer,
            ];
        }

        $chosen = $sortedOptions->firstWhere('id', $resolvedAnswer);

        return [
            'resolvedAnswer' => $resolvedAnswer,
            'delta' => $chosen instanceof ModifierOption ? ModifierAnswerPrice::forSelect($chosen->addOn())->amount : Money::zero(),
            'displayAnswer' => $chosen instanceof ModifierOption ? $chosen->label : $resolvedAnswer,
            'options' => $options,
        ];
    }

    /**
     * @return array{resolvedAnswer: string, delta: Money, displayAnswer: string, options: list<array{id: string, label: string, delta: Money, selected: bool}>}
     */
    private static function resolveTextAnswer(Modifier $modifier, ?string $rawAnswer): array
    {
        $resolvedAnswer = $rawAnswer !== null ? trim($rawAnswer) : '';

        return [
            'resolvedAnswer' => $resolvedAnswer,
            'delta' => $resolvedAnswer === '' ? Money::zero() : ModifierAnswerPrice::forText(Money::fromCents($modifier->add_on_price_cents))->amount,
            'displayAnswer' => $resolvedAnswer,
            'options' => [],
        ];
    }

    /**
     * @return array{resolvedAnswer: string, delta: Money, displayAnswer: string, options: list<array{id: string, label: string, delta: Money, selected: bool}>}
     */
    private static function resolveMeasurementAnswer(Modifier $modifier, ?string $rawAnswer): array
    {
        $trimmed = $rawAnswer !== null ? trim($rawAnswer) : '';
        $isNumeric = $trimmed !== '' && is_numeric($trimmed);

        return [
            'resolvedAnswer' => $isNumeric ? $trimmed : '',
            'delta' => $isNumeric
                ? ModifierAnswerPrice::forMeasurement((float) $trimmed, $modifier->rate_cents_per_unit === null ? null : Money::fromCents($modifier->rate_cents_per_unit))->amount
                : Money::zero(),
            'displayAnswer' => $isNumeric ? $trimmed.($modifier->unit !== null ? ' '.$modifier->unit : '') : '',
            'options' => [],
        ];
    }
}
