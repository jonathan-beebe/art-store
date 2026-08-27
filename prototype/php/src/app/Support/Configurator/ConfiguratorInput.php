<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use Illuminate\Http\Request;

/**
 * The buyer's raw, unvalidated choices off one request — a GET's query
 * string or a POST's body, read the same way either time, so
 * {@see ConfiguratorPageResolver} does not care which one handed it these.
 */
final readonly class ConfiguratorInput
{
    /**
     * @param  array<string, string>  $axisSelections  axis id => option value id
     * @param  array<string, string>  $modifierAnswers  modifier id => raw answer
     */
    private function __construct(
        public array $axisSelections,
        public ?string $unitId,
        public array $modifierAnswers,
        public int $quantity,
    ) {}

    /**
     * @param  array<string, string>  $axisSelections
     * @param  array<string, string>  $modifierAnswers
     */
    public static function of(array $axisSelections, ?string $unitId, array $modifierAnswers, int $quantity): self
    {
        return new self($axisSelections, $unitId, $modifierAnswers, max(1, $quantity));
    }

    /**
     * Reads the same four fields off either a GET query string or a POST
     * body, tolerating whatever shape a tampered request sends instead of
     * raising — a bad value just resolves to the listing's own default.
     */
    public static function fromRaw(mixed $axis, mixed $unit, mixed $modifier, mixed $quantity): self
    {
        return self::of(
            is_array($axis) ? self::stringMap($axis) : [],
            is_string($unit) ? $unit : null,
            is_array($modifier) ? self::stringMap($modifier) : [],
            is_string($quantity) && ctype_digit($quantity) ? (int) $quantity : 1,
        );
    }

    /**
     * The same four fields, read off a GET request's query string — the
     * page render and its rate-limit-tripped re-render both read from here.
     */
    public static function fromQuery(Request $request): self
    {
        return self::fromRaw(
            $request->query('axis', []),
            $request->query('unit'),
            $request->query('modifier', []),
            $request->query('quantity'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private static function stringMap(array $values): array
    {
        $strings = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $strings[$key] = $value;
            }
        }

        return $strings;
    }
}
