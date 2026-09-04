<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * Which way a seller moves one section through the order of a store page.
 */
enum StoreSectionMove: string
{
    case Up = 'up';
    case Down = 'down';

    /** The step this move adds to a section's position. */
    public function offset(): int
    {
        return $this === self::Up ? -1 : 1;
    }
}
