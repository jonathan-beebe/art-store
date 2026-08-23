<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;

final class ListingStatusTally
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, int>  $countsByStatus  status value => count
     * @return list<ListingStatusCount>
     */
    public static function from(array $countsByStatus): array
    {
        return array_map(
            fn (ListingStatus $status): ListingStatusCount => ListingStatusCount::of(
                $status,
                $countsByStatus[$status->value] ?? 0,
            ),
            ListingStatus::cases(),
        );
    }

    /**
     * @param  list<ListingStatusCount>  $tally
     */
    public static function total(array $tally): int
    {
        return array_sum(array_map(fn (ListingStatusCount $row): int => $row->count, $tally));
    }
}
