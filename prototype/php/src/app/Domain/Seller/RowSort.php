<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * Orders a seller table's rows by one {@see TableSort} — the key the
 * sort's column reads off each row, `$idOf` breaking a tie ascending
 * whichever way the column runs, so two rows never read as equal and the
 * order never depends on the rows' input order.
 */
final class RowSort
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @template TRow of object
     *
     * @param  TableSort<TRow>  $sort
     * @param  list<TRow>  $rows
     * @param  callable(TRow): (int|string)  $idOf  the tie-break key, read ascending whichever way the column sorts
     * @return list<TRow>
     */
    public static function apply(TableSort $sort, array $rows, callable $idOf): array
    {
        /** @var list<array{0: int|float|string, 1: int|string, 2: TRow}> $decorated */
        $decorated = array_map(
            fn (object $row): array => [$sort->column->keyOf($row), $idOf($row), $row],
            $rows,
        );

        usort($decorated, function (array $a, array $b) use ($sort): int {
            $result = is_string($a[0]) && is_string($b[0]) ? strcmp($a[0], $b[0]) : $a[0] <=> $b[0];

            if ($result !== 0) {
                return $sort->direction->isAscending() ? $result : -$result;
            }

            return $a[1] <=> $b[1];
        });

        /** @var list<TRow> $ordered */
        $ordered = array_map(fn (array $entry): object => $entry[2], $decorated);

        return $ordered;
    }
}
