<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * Orders a listing table's rows by one {@see ListingSort} — every key the
 * sort's column reads off each row, id breaking a tie, so two rows never
 * read as equal and the order never depends on the rows' input order.
 */
final class ListingTableSort
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<ListingTableRow>  $rows
     * @return list<ListingTableRow>
     */
    public static function apply(ListingSort $sort, array $rows): array
    {
        $sorted = $rows;

        usort($sorted, function (ListingTableRow $a, ListingTableRow $b) use ($sort): int {
            $result = self::compare($a, $b, $sort->column);

            return $sort->direction->isAscending() ? $result : -$result;
        });

        return $sorted;
    }

    private static function compare(ListingTableRow $a, ListingTableRow $b, ListingSortColumn $column): int
    {
        $keyA = $column->keyOf($a);
        $keyB = $column->keyOf($b);

        $result = is_string($keyA) && is_string($keyB) ? strcmp($keyA, $keyB) : $keyA <=> $keyB;

        return $result !== 0 ? $result : $a->id <=> $b->id;
    }
}
