<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Models\Order;

/**
 * The distinct listings one order spans, for the `listing_ids` an
 * `order.place`, `order.pay`, or `order.cancel` analytics event carries in
 * its data — an order spans listings, so a per-listing funnel needs them
 * without a join back to the commerce database.
 */
final class OrderListingIds
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<string>
     */
    public static function of(Order $order): array
    {
        /** @var list<string> $listingIds */
        $listingIds = $order->items()->pluck('listing_id')->unique()->values()->all();

        return $listingIds;
    }
}
