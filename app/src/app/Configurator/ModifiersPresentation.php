<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\ModifierAnswerPrice;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Money\Money;
use App\Models\Modifier;
use App\Models\ModifierOption;
use Illuminate\Database\Eloquent\Collection;

/**
 * The configurator page's modifier questions, each folded against the
 * buyer's raw answer (or its default) into the shape the panel renders,
 * alongside the raw answers and their display snapshot that
 * {@see \App\Domain\Configurator\ConfigurationPricing} and the add-to-cart fingerprint need. A
 * modifier out of scope for the buyer's current axis selection is left out
 * entirely.
 */
final class ModifiersPresentation
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  Collection<int, Modifier>  $modifierModels
     * @param  list<string>  $selectedOptionValueIds
     * @param  array<string, string>  $rawAnswersByModifierId
     * @return array{0: list<array{id: string, prompt: string, instructions: ?string, kind: ModifierKind, required: bool, charLimit: ?int, unit: ?string, minValue: ?float, maxValue: ?float, addOnPriceCents: int, options: list<array{id: string, label: string, delta: Money, selected: bool}>, answer: string, delta: Money}>, 1: array<string, string>, 2: array<string, array{prompt: string, answer: string, raw: string}>}
     */
    public static function build(Collection $modifierModels, array $selectedOptionValueIds, array $rawAnswersByModifierId): array
    {
        $presentation = [];
        $rawAnswers = [];
        $answersSnapshot = [];

        foreach ($modifierModels as $modifier) {
            if (! $modifier->appliesTo($selectedOptionValueIds)) {
                continue;
            }

            $answer = self::answerFor($modifier, $rawAnswersByModifierId[$modifier->id] ?? null);
            $presentation[] = self::present($modifier, $answer);

            if ($answer['resolvedAnswer'] === '') {
                continue;
            }

            $rawAnswers[$modifier->id] = $answer['resolvedAnswer'];
            $answersSnapshot[$modifier->id] = [
                'prompt' => $modifier->prompt,
                'answer' => $answer['displayAnswer'],
                'raw' => $answer['resolvedAnswer'],
            ];
        }

        return [$presentation, $rawAnswers, $answersSnapshot];
    }

    /**
     * @param  array{resolvedAnswer: string, delta: Money, displayAnswer: string, options: list<array{id: string, label: string, delta: Money, selected: bool}>}  $answer
     * @return array{id: string, prompt: string, instructions: ?string, kind: ModifierKind, required: bool, charLimit: ?int, unit: ?string, minValue: ?float, maxValue: ?float, addOnPriceCents: int, options: list<array{id: string, label: string, delta: Money, selected: bool}>, answer: string, delta: Money}
     */
    private static function present(Modifier $modifier, array $answer): array
    {
        return [
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
            'options' => $answer['options'],
            'answer' => $answer['resolvedAnswer'],
            'delta' => $answer['delta'],
        ];
    }

    /**
     * @return array{resolvedAnswer: string, delta: Money, displayAnswer: string, options: list<array{id: string, label: string, delta: Money, selected: bool}>}
     */
    private static function answerFor(Modifier $modifier, ?string $rawAnswer): array
    {
        return match ($modifier->kind) {
            ModifierKind::Select => self::selectAnswer($modifier, $rawAnswer),
            ModifierKind::Text => self::textAnswer($modifier, $rawAnswer),
            ModifierKind::Measurement => self::measurementAnswer($modifier, $rawAnswer),
        };
    }

    /**
     * @return array{resolvedAnswer: string, delta: Money, displayAnswer: string, options: list<array{id: string, label: string, delta: Money, selected: bool}>}
     */
    private static function selectAnswer(Modifier $modifier, ?string $rawAnswer): array
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
    private static function textAnswer(Modifier $modifier, ?string $rawAnswer): array
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
    private static function measurementAnswer(Modifier $modifier, ?string $rawAnswer): array
    {
        $trimmed = $rawAnswer !== null ? trim($rawAnswer) : '';

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return ['resolvedAnswer' => '', 'delta' => Money::zero(), 'displayAnswer' => '', 'options' => []];
        }

        return [
            'resolvedAnswer' => $trimmed,
            'delta' => self::measurementDelta($modifier, (float) $trimmed),
            'displayAnswer' => self::measurementDisplayAnswer($modifier, $trimmed),
            'options' => [],
        ];
    }

    private static function measurementDelta(Modifier $modifier, float $value): Money
    {
        $rate = $modifier->rate_cents_per_unit === null ? null : Money::fromCents($modifier->rate_cents_per_unit);

        return ModifierAnswerPrice::forMeasurement($value, $rate)->amount;
    }

    private static function measurementDisplayAnswer(Modifier $modifier, string $trimmed): string
    {
        return $modifier->unit === null ? $trimmed : "{$trimmed} {$modifier->unit}";
    }
}
