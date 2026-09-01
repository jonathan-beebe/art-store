<?php

declare(strict_types=1);

namespace App\Domain\Customers;

it('holds a listing and the quantity of it in a cart', function (): void {
    $line = new CustomerCartLine('lst_00000000000000000000000001', 2);

    expect($line->listingId)->toBe('lst_00000000000000000000000001')
        ->and($line->quantity)->toBe(2);
});

it('holds the fingerprint that tells two configured lines of the same listing apart', function (): void {
    $line = new CustomerCartLine('lst_00000000000000000000000001', 1, 'fp_engraved_gold');

    expect($line->fingerprint)->toBe('fp_engraved_gold');
});
