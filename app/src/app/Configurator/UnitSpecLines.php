<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\UnitSpecLabel;

/**
 * Formats every entry of a piece's `specs_json` into one humanized line
 * apiece, in the same words the buyer-facing configurator already shows.
 */
final class UnitSpecLines
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, int|float|string|bool>|null  $specs
     * @return list<string>
     */
    public static function format(?array $specs): array
    {
        if ($specs === null) {
            return [];
        }

        return array_map(
            fn (string $key, int|float|string|bool $value): string => UnitSpecLabel::format($key, $value),
            array_keys($specs),
            array_values($specs),
        );
    }
}
