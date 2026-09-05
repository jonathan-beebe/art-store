<?php

declare(strict_types=1);

namespace App\Domain\Listings;

/**
 * How the admin listings list is narrowed by removal state. `Any` is what an
 * absent, empty, or unrecognised `removed=` means — the same value the
 * console's own "Any" option submits — so the scope that reads this filter
 * treats it exactly like the null a missing filter already means.
 */
enum RemovedFilter: string
{
    case Any = 'any';
    case Removed = 'removed';
    case Visible = 'visible';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any',
            self::Removed => 'Removed',
            self::Visible => 'Visible',
        };
    }
}
