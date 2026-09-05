<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\AxisDefaults;
use App\Domain\Configurator\AxisSelectionResolver;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use Illuminate\Database\Eloquent\Collection;

/**
 * The buyer's option value per axis — one of the buyer's raw choices where
 * given and valid, the seller's default otherwise — resolved against a
 * listing's axis models by {@see AxisSelectionResolver}, plus the option
 * value rows the rest of the page needs by id. An axis with no option values
 * yet contributes no default and so no entry in the selection.
 */
final class SelectedAxisValues
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  Collection<int, OptionAxis>  $axisModels
     * @param  array<string, string>  $axisSelections  axis id => the buyer's requested option value id
     * @return array{0: array<string, string>, 1: list<OptionValue>, 2: array<string, OptionValue>}
     */
    public static function resolve(Collection $axisModels, array $axisSelections): array
    {
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

        $selected = AxisSelectionResolver::resolve($axisDefaults, $axisSelections);
        $selectedOptionValues = array_values(array_map(fn (string $id): OptionValue => $optionValueById[$id], $selected));

        return [$selected, $selectedOptionValues, $optionValueById];
    }
}
