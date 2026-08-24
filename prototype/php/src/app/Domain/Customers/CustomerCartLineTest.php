<?php

declare(strict_types=1);

namespace App\Domain\Customers;

it('holds a listing and the quantity of it in a cart', function (): void {
    $line = new CustomerCartLine('lst_00000000000000000000000001', 2);

    expect($line->listingId)->toBe('lst_00000000000000000000000001')
        ->and($line->quantity)->toBe(2);
});
