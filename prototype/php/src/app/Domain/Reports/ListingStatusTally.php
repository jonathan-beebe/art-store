<?php

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;

final class ListingStatusTally
{
    /**
     * @param  array<string, int>  $countsByStatus  status value => count
     * @return list<ListingStatusCount>
     */
    public static function from(array $countsByStatus): array
    {
        return array_map(
            fn (ListingStatus $status): ListingStatusCount => new ListingStatusCount(
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
