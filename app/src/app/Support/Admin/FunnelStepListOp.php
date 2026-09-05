<?php

declare(strict_types=1);

namespace App\Support\Admin;

/**
 * One editor action on a funnel's working step-name list, applied without a
 * round trip through validation — the funnel editor's "Add step", "Remove",
 * "Move up", and "Move down" buttons each post one of these back to the
 * same page, and the controller re-renders the form with the result,
 * unsaved. An op names its target row by index: `remove_step:2`,
 * `move_up:1`, `move_down:1`; `add_step` carries none. An op naming a row
 * outside the list, or any other value (`save` included), changes nothing.
 */
final class FunnelStepListOp
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<string>  $steps
     * @return list<string>
     */
    public static function apply(array $steps, string $op): array
    {
        return match (true) {
            $op === 'add_step' => [...$steps, ''],
            str_starts_with($op, 'remove_step:') => self::removed($steps, self::index($op)),
            str_starts_with($op, 'move_up:') => self::swapped($steps, self::index($op), -1),
            str_starts_with($op, 'move_down:') => self::swapped($steps, self::index($op), 1),
            default => $steps,
        };
    }

    /**
     * @param  list<string>  $steps
     * @return list<string>
     */
    private static function removed(array $steps, ?int $index): array
    {
        if ($index === null || ! array_key_exists($index, $steps)) {
            return $steps;
        }

        unset($steps[$index]);

        return array_values($steps);
    }

    /**
     * @param  list<string>  $steps
     * @return list<string>
     */
    private static function swapped(array $steps, ?int $index, int $by): array
    {
        $target = $index === null ? null : $index + $by;

        if ($index === null || $target === null || ! array_key_exists($index, $steps) || ! array_key_exists($target, $steps)) {
            return $steps;
        }

        [$steps[$index], $steps[$target]] = [$steps[$target], $steps[$index]];

        return array_values($steps);
    }

    private static function index(string $op): ?int
    {
        $value = explode(':', $op, 2)[1] ?? null;

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
