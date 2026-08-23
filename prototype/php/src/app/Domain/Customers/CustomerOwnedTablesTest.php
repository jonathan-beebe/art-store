<?php

declare(strict_types=1);

namespace App\Domain\Customers;

it('covers every table holding a customer foreign key that a merge re-points', function (): void {
    expect(array_keys(CustomerOwnedTables::all()))
        ->toBe(['favorites', 'carts', 'orders', 'listing_events', 'conversations', 'customer_blocks']);
});

it('names the column holding the customer for every table', function (): void {
    foreach (CustomerOwnedTables::all() as $column) {
        expect($column)->toBe('customer_id');
    }
});
