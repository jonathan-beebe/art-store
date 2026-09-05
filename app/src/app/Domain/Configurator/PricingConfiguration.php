<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * Everything {@see ConfigurationPricing} needs to price one configuration,
 * folded off the listing's rows by the shell: the base price, the selected
 * options with their axes' pricing modes, the one override that wins (a
 * unit's before a variant's), the modifiers with their scopes, the raw
 * answers, and the quantity tiers.
 */
final readonly class PricingConfiguration
{
    /**
     * @param  list<PricedOption>  $selected
     * @param  list<PricedModifier>  $modifiers
     * @param  array<string, string>  $answers  modifier id => raw answer
     * @param  list<QuantityDiscount>  $tiers
     */
    private function __construct(
        public Money $basePrice,
        public bool $hasStandaloneAxis,
        public array $selected,
        public ?Money $override,
        public array $modifiers,
        public array $answers,
        public array $tiers,
    ) {}

    /**
     * @param  list<PricedOption>  $selected
     * @param  list<PricedModifier>  $modifiers
     * @param  array<string, string>  $answers
     * @param  list<QuantityDiscount>  $tiers
     */
    public static function of(
        Money $basePrice,
        bool $hasStandaloneAxis,
        array $selected,
        ?Money $override,
        array $modifiers,
        array $answers,
        array $tiers,
    ): self {
        return new self($basePrice, $hasStandaloneAxis, $selected, $override, $modifiers, $answers, $tiers);
    }
}
