<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\PriceBreakdown;
use App\Domain\Configurator\PricingMode;
use App\Domain\Money\Money;

/**
 * Everything one render of `/art/{slug}` (or one add-to-cart POST) needs,
 * resolved by {@see ConfiguratorPageResolver} from a listing's configurator
 * rows and the buyer's raw choices: the axis controls with their deltas and
 * grey-outs, the unit picker if the matched variant is serialized, the
 * modifiers currently in scope, the quantity-break table, and the itemized
 * price panel for the current selection.
 */
final readonly class ListingConfiguration
{
    /**
     * @param  list<array{id: string, name: string, pricingMode: PricingMode, options: list<array{id: string, label: string, delta: Money, price: Money, selected: bool, selectable: bool, reason: ?string}>}>  $axes
     * @param  array<string, string>  $selectedOptionValueIdsByAxis
     * @param  list<array{id: string, label: string, conditionNote: ?string, specLines: list<string>, price: Money, selected: bool}>  $units
     * @param  list<array{id: string, prompt: string, instructions: ?string, kind: ModifierKind, required: bool, charLimit: ?int, unit: ?string, minValue: ?float, maxValue: ?float, addOnPriceCents: int, options: list<array{id: string, label: string, delta: Money, selected: bool}>, answer: string, delta: Money}>  $modifiers
     * @param  list<array{minQty: int, discountPercent: float, active: bool}>  $quantityTiers
     * @param  list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>  $configurationSnapshot
     * @param  array<string, array{prompt: string, answer: string, raw: string}>  $answersSnapshot
     * @param  array<string, string>  $fingerprintAnswers
     */
    public function __construct(
        public bool $hasConfigurator,
        // Whether the listing holds any variant row at all — distinct from
        // `hasConfigurator`, since a listing can carry a modifier alone (no
        // axes, no variant) and still price and check stock straight off
        // `listings.price_cents`/`quantity` the way the legacy path does.
        // True here is what makes a combination with no matching variant
        // (the sparse table's "not offered" cell) refuse as unavailable
        // rather than silently falling back to that legacy check.
        public bool $hasVariants,
        public array $axes,
        public array $selectedOptionValueIdsByAxis,
        public ?string $variantId,
        public bool $isSerialized,
        public array $units,
        public ?string $selectedUnitId,
        public array $modifiers,
        public int $quantity,
        public array $quantityTiers,
        public PriceBreakdown $breakdown,
        public bool $canAddToCart,
        public ?string $unavailableReason,
        public array $configurationSnapshot,
        public array $answersSnapshot,
        public array $fingerprintAnswers,
    ) {}
}
