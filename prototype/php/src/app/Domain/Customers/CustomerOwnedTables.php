<?php

declare(strict_types=1);

namespace App\Domain\Customers;

final class CustomerOwnedTables
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Tables whose rows move with the customer when an anonymous identity is
     * merged into a verified one by writing one column and nothing else.
     * Notifications, sent messages, and conversations move too, but each
     * carries something a blind column write would leave behind — a morph
     * type beside the id, a `subject_key` naming the participant — so
     * `MergeAnonymousCustomer` re-points those through their models instead.
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
            'customer_blocks' => 'customer_id',
        ];
    }
}
