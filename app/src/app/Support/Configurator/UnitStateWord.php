<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\UnitState;

/**
 * The seller-facing word for a piece's state, hand-mapped so a state the
 * schema still carries but the product dropped (`reserved`) reads as the
 * plain "on hold." Its internal name never surfaces.
 */
final class UnitStateWord
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forState(UnitState $state): string
    {
        return match ($state) {
            UnitState::Available => 'available',
            UnitState::Sold => 'sold',
            UnitState::Reserved => 'on hold',
        };
    }
}
