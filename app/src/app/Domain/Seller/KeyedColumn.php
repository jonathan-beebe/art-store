<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * A {@see SortableColumn} whose order {@see RowSort} reads off a row in
 * PHP — the listings table's columns. The customers table sorts in SQL
 * and never reads a key this way.
 *
 * @template TRow of object
 *
 * @extends SortableColumn<TRow>
 */
interface KeyedColumn extends SortableColumn
{
    /**
     * The value one row sorts by on this column.
     *
     * @param  TRow  $row
     */
    public function keyOf(object $row): int|float|string;
}
