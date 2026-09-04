<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\SortableColumn;
use App\Domain\Seller\TableSort;
use BackedEnum;

/**
 * One `ColumnHeader` per sortable column of a seller table — the listings
 * table and the customers table both build their header row this way, off
 * whichever `SortableColumn` enum the table sorts by.
 */
final class ColumnHeaders
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @template TRow of object
     * @template TColumn of SortableColumn<TRow>&BackedEnum
     *
     * @param  array<string, string>  $roundTripped
     * @param  TableSort<TRow>  $sort
     * @param  list<TColumn>  $columns
     * @return list<ColumnHeader>
     */
    public static function for(string $routeName, array $roundTripped, TableSort $sort, array $columns): array
    {
        $without = collect($roundTripped)->except(['sort', 'dir'])->all();

        return array_map(fn (SortableColumn&BackedEnum $column): ColumnHeader => new ColumnHeader(
            column: $column,
            href: route($routeName, [...$without, 'sort' => $column->value, 'dir' => $sort->nextDirectionFor($column)->value]),
            ariaSort: $sort->ariaSort($column) ?? 'none',
        ), $columns);
    }
}
