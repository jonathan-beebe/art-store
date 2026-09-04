<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * The state of one serialized (one-of-a-kind) unit of stock.
 */
enum UnitState: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';

    public function isAvailable(): bool
    {
        return $this === self::Available;
    }
}
