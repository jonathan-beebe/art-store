<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\UnitState;
use App\Models\Unit;

/**
 * Tallies a combination's pieces by state — what the "N pieces · X
 * available · Y sold" count line reads off, with an on-hold figure the view
 * shows only when a reserved piece actually exists.
 */
final class UnitStateCounts
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  iterable<Unit>  $units
     * @return array{total: int, available: int, sold: int, onHold: int}
     */
    public static function tally(iterable $units): array
    {
        $available = 0;
        $sold = 0;
        $onHold = 0;
        $total = 0;

        foreach ($units as $unit) {
            $total++;

            match ($unit->state) {
                UnitState::Available => $available++,
                UnitState::Sold => $sold++,
                UnitState::Reserved => $onHold++,
            };
        }

        return ['total' => $total, 'available' => $available, 'sold' => $sold, 'onHold' => $onHold];
    }
}
