<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ListingSortColumn;

/**
 * One sortable header of the listings table: the column it sorts by, the
 * link a click follows, and the `aria-sort` value that header carries.
 */
final readonly class ColumnHeader
{
    public function __construct(
        public ListingSortColumn $column,
        public string $href,
        public string $ariaSort,
    ) {}
}
