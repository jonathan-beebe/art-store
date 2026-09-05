<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * One modifier as the pricer reads it: what it asks, how its answer is
 * priced, and which option values scope it. `scopedTo` empty means the
 * modifier applies to every configuration.
 */
final readonly class PricedModifier
{
    /**
     * @param  list<PricedModifierOption>  $options
     * @param  list<string>  $scopedTo  option value ids; empty scopes to every selection
     */
    private function __construct(
        public string $id,
        public string $prompt,
        public ModifierKind $kind,
        public Money $addOn,
        public ?Money $ratePerUnit,
        public array $options,
        public array $scopedTo,
    ) {}

    /**
     * @param  list<PricedModifierOption>  $options
     * @param  list<string>  $scopedTo
     */
    public static function of(
        string $id,
        string $prompt,
        ModifierKind $kind,
        Money $addOn,
        ?Money $ratePerUnit,
        array $options,
        array $scopedTo,
    ): self {
        return new self($id, $prompt, $kind, $addOn, $ratePerUnit, $options, $scopedTo);
    }

    /**
     * @param  list<string>  $selectedOptionValueIds
     */
    public function appliesTo(array $selectedOptionValueIds): bool
    {
        return $this->scopedTo === [] || array_intersect($this->scopedTo, $selectedOptionValueIds) !== [];
    }

    /**
     * What a raw answer adds per unit: the flat add-on for text, the chosen
     * option's add-on for a select, the rate times the value for a
     * measurement. An answer naming no option charges nothing.
     */
    public function priceAnswer(string $raw): Money
    {
        return match ($this->kind) {
            ModifierKind::Text => ModifierAnswerPrice::forText($this->addOn)->amount,
            ModifierKind::Select => ModifierAnswerPrice::forSelect($this->selectedAddOn($raw))->amount,
            ModifierKind::Measurement => ModifierAnswerPrice::forMeasurement(
                is_numeric($raw) ? (float) $raw : 0.0,
                $this->ratePerUnit,
            )->amount,
        };
    }

    /**
     * The breakdown line's label for an answer: the prompt, and for a
     * select the chosen option's label after it.
     */
    public function answerLabel(string $raw): string
    {
        $option = $this->kind === ModifierKind::Select ? $this->option($raw) : null;

        return $option === null ? $this->prompt : "{$this->prompt}: {$option->label}";
    }

    private function selectedAddOn(string $raw): Money
    {
        $option = $this->option($raw);

        return $option === null ? Money::zero() : $option->addOn;
    }

    private function option(string $id): ?PricedModifierOption
    {
        foreach ($this->options as $option) {
            if ($option->id === $id) {
                return $option;
            }
        }

        return null;
    }
}
