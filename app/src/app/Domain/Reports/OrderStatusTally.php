<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\OrderStatus;

/**
 * Every order status the dashboard shows, `payment_failed` and `cancelled`
 * included even at zero — a `group by` answers only for the statuses that
 * have rows, and a dashboard that hides one is lying about the state
 * machine.
 */
final class OrderStatusTally
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, int>  $countsByStatus  status value => count
     * @return list<OrderStatusCount>
     */
    public static function from(array $countsByStatus): array
    {
        return array_map(
            fn (OrderStatus $status): OrderStatusCount => OrderStatusCount::of(
                $status,
                $countsByStatus[$status->value] ?? 0,
            ),
            OrderStatus::cases(),
        );
    }
}
