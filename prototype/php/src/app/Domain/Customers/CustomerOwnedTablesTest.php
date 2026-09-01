<?php

declare(strict_types=1);

namespace App\Domain\Customers;

it('covers every table holding a customer foreign key that a merge blindly re-points', function (): void {
    expect(array_keys(CustomerOwnedTables::all()))
        ->toBe(['orders', 'order_items', 'fulfillments', 'payments', 'refunds', 'listing_events', 'customer_blocks']);
});

it('names the column holding the customer for every table', function (): void {
    foreach (CustomerOwnedTables::all() as $column) {
        expect($column)->toBe('customer_id');
    }
});

it('names why every left-behind table is not blindly re-pointed', function (): void {
    foreach (CustomerOwnedTables::leftBehind() as $table => $reason) {
        expect($table)->toBeString()
            ->and($reason)->not->toBe('');
    }
});
