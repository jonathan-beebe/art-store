<?php

namespace App\Domain\Customers;

final class CustomerOwnedTables
{
    /**
     * Tables whose rows move with the customer when an anonymous identity is
     * merged into a verified one.
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
            'notifications' => 'customer_id',
        ];
    }
}
