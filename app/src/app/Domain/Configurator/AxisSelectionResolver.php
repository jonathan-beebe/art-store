<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Resolves every axis's selection from the buyer's query-string choices, so
 * a missing or tampered-with axis (no query param yet, or an id belonging to
 * a different axis) falls back to its default instead of leaving the page
 * with nothing to price — "defaults preselected... so the page opens with a
 * concrete price."
 */
final class AxisSelectionResolver
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<AxisDefaults>  $axes
     * @param  array<string, string>  $requested  axis id => option value id, from the query string
     * @return array<string, string> axis id => resolved option value id
     */
    public static function resolve(array $axes, array $requested): array
    {
        $resolved = [];

        foreach ($axes as $axis) {
            $candidate = $requested[$axis->axisId] ?? null;

            $resolved[$axis->axisId] = $candidate !== null && in_array($candidate, $axis->optionValueIds, true)
                ? $candidate
                : $axis->defaultOptionValueId;
        }

        return $resolved;
    }
}
