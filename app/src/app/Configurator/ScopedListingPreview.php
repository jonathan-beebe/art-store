<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Models\Modifier;
use App\Models\OptionValue;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

/**
 * The two "what buyers see" selections a scoped question earns on the
 * questions screen: one where the scope matches, so the question appears,
 * and a sibling selection on the same choice where it doesn't — built off
 * the listing's first scoped question, since one pair already makes the
 * point that a hidden question is gone, not disabled.
 */
final readonly class ScopedListingPreview
{
    private function __construct(
        public ConfiguratorInput $appliesInput,
        public string $appliesCaption,
        public ConfiguratorInput $otherInput,
        public string $otherCaption,
    ) {}

    /**
     * @param  Collection<int, Modifier>  $modifiers  each with `scopes.optionValue.axis.optionValues` eager-loaded
     */
    public static function resolve(Collection $modifiers): ?self
    {
        $scopedModifier = $modifiers->first(fn (Modifier $modifier): bool => $modifier->scopes->isNotEmpty());

        if (! $scopedModifier instanceof Modifier) {
            return null;
        }

        $scopedValue = $scopedModifier->scopes->first()->optionValue ?? throw new LogicException('A modifier scope always names an option value.');
        $axis = $scopedValue->axis ?? throw new LogicException('An option value always belongs to an axis.');

        $sibling = $axis->optionValues->first(fn (OptionValue $value): bool => $value->id !== $scopedValue->id);

        if (! $sibling instanceof OptionValue) {
            return null;
        }

        return new self(
            appliesInput: ConfiguratorInput::of([$axis->id => $scopedValue->id], null, [], 1),
            appliesCaption: "{$axis->name}: {$scopedValue->label}",
            otherInput: ConfiguratorInput::of([$axis->id => $sibling->id], null, [], 1),
            otherCaption: "{$axis->name}: {$sibling->label}",
        );
    }

    /**
     * The label of an option value that would keep a scoped question from
     * showing — the concrete example the "buyers who pick…" sentence names,
     * favoring a sibling on the scoped axis itself.
     */
    public static function unaffectedOptionLabel(Modifier $modifier): ?string
    {
        if ($modifier->scopes->isEmpty()) {
            return null;
        }

        $scopedIds = $modifier->scopes->pluck('option_value_id')->all();

        foreach ($modifier->scopes as $scope) {
            $optionValue = $scope->optionValue ?? throw new LogicException('A modifier scope always names an option value.');
            $axis = $optionValue->axis ?? throw new LogicException('An option value always belongs to an axis.');

            $sibling = $axis->optionValues->first(fn (OptionValue $value): bool => ! in_array($value->id, $scopedIds, true));

            if ($sibling instanceof OptionValue) {
                return $sibling->label;
            }
        }

        return null;
    }
}
