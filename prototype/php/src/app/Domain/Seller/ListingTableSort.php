<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * Orders a listing table's rows by one {@see ListingSort} — a stable sort
 * over whatever key that sort's column reads off each row.
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
            $result = self::compare($sort->column->keyOf($a), $sort->column->keyOf($b));

            return $sort->direction->isAscending() ? $result : -$result;
        });

        return $sorted;
    }

    private static function compare(int|float|string $a, int|float|string $b): int
    {
        return is_string($a) && is_string($b) ? strcmp($a, $b) : $a <=> $b;
    }
}
