<?php

declare(strict_types=1);

namespace App\Domain\Seller;

enum ListingSortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function flipped(): self
    {
        return $this === self::Asc ? self::Desc : self::Asc;
    }

    public function isAscending(): bool
    {
        return $this === self::Asc;
    }

    /** The `aria-sort` value a sorted column header carries. */
    public function ariaSort(): string
    {
        return $this === self::Asc ? 'ascending' : 'descending';
    }
}
