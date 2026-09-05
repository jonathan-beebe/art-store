<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\FulfillmentStatus;

/**
 * Every fulfillment status the dashboard shows, `declined` and `refunded`
 * included even at zero — the same zero-filling rule as
 * {@see OrderStatusTally} and {@see ListingStatusTally}.
 */
final class FulfillmentStatusTally
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, int>  $countsByStatus  status value => count
     * @return list<FulfillmentStatusCount>
     */
    public static function from(array $countsByStatus): array
    {
        return array_map(
            fn (FulfillmentStatus $status): FulfillmentStatusCount => FulfillmentStatusCount::of(
                $status,
                $countsByStatus[$status->value] ?? 0,
            ),
            FulfillmentStatus::cases(),
        );
    }
}
