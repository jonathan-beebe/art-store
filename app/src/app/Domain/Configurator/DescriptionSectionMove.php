<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Which neighbor a description section trades places with — the whole of
 * "reorder" offered to a seller with no drag-and-drop, JavaScript off.
 */
enum DescriptionSectionMove: string
{
    case Up = 'up';
    case Down = 'down';
}
