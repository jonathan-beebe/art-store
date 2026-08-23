<?php

declare(strict_types=1);

namespace App\Domain\Customers;

final class CustomerOwnedTables
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Tables holding a customer foreign key whose rows move with the customer
     * when an anonymous identity is merged into a verified one. Notifications
     * and sent messages move too, but they name their recipient or sender by
     * morph type and id, so `MergeAnonymousCustomer` re-points them through
     * the relation instead.
     *
     * @return array<string, string> table name => column holding the customer id
     */
    public static function all(): array
    {
        return [
            'favorites' => 'customer_id',
            'carts' => 'customer_id',
            'orders' => 'customer_id',
            'listing_events' => 'customer_id',
            'conversations' => 'customer_id',
            'customer_blocks' => 'customer_id',
        ];
    }
}
