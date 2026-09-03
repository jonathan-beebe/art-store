<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Which way a {@see RangeChange} reads — the sign that decides which of
 * the admin chrome's up/down/flat colors a change renders in.
 */
enum ChangeDirection
{
    case Up;
    case Down;
    case Flat;
}
