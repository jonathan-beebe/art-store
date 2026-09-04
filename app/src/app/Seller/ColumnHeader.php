<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\SortableColumn;

/**
 * One sortable header of a seller table: the column it sorts by, the link a
 * click follows, and the `aria-sort` value that header carries.
 */
final readonly class ColumnHeader
{
    /**
     * @param  SortableColumn<covariant object>  $column
     */
    public function __construct(
        public SortableColumn $column,
        public string $href,
        public string $ariaSort,
    ) {}
}
