<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * The shape a catalog property's values take: an enumerated list, free text,
 * or a number. Only `Enum` carries `property_value` rows.
 */
enum PropertyDataType: string
{
    case Enum = 'enum';
    case Text = 'text';
    case Number = 'number';

    public function enumeratesValues(): bool
    {
        return $this === self::Enum;
    }
}
