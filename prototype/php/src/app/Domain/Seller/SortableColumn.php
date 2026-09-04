<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * A column a seller table sorts by. `App\Seller\ColumnHeader` carries one
 * of these, so the listings table and the customers table render their
 * headers through the same value object.
 *
 * @template TRow of object
 */
interface SortableColumn
{
    public function label(): string;

    /** Whether the column's cells sit against the right edge. */
    public function alignsRight(): bool;
}
