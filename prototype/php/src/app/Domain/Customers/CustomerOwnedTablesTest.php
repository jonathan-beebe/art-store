<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use PHPUnit\Framework\TestCase;

final class CustomerOwnedTablesTest extends TestCase
{
    public function test_it_covers_every_table_a_merge_re_points(): void
    {
        $this->assertSame(
            ['favorites', 'carts', 'orders', 'listing_events', 'notifications'],
            array_keys(CustomerOwnedTables::all()),
        );
    }

    public function test_every_table_names_the_column_holding_the_customer(): void
    {
        foreach (CustomerOwnedTables::all() as $column) {
            $this->assertSame('customer_id', $column);
        }
    }
}
