<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * Orders the customers table's rows by one {@see CustomerSort} — the key
 * the sort's column reads off each row, the customer id breaking a tie, so
 * two rows never read as equal and the order never depends on the rows'
 * input order.
 */
final class CustomerTableSort
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<CustomerRow>  $rows
     * @return list<CustomerRow>
     */
    public static function apply(CustomerSort $sort, array $rows): array
    {
        $sorted = $rows;

        usort($sorted, function (CustomerRow $a, CustomerRow $b) use ($sort): int {
            $result = self::compare($a, $b, $sort->column);

            return $sort->direction->isAscending() ? $result : -$result;
        });

        return $sorted;
    }

    private static function compare(CustomerRow $a, CustomerRow $b, CustomerSortColumn $column): int
    {
        $keyA = $column->keyOf($a);
        $keyB = $column->keyOf($b);

        $result = is_string($keyA) && is_string($keyB) ? strcmp($keyA, $keyB) : $keyA <=> $keyB;

        return $result !== 0 ? $result : $a->customerId <=> $b->customerId;
    }
}
