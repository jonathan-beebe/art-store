<?php

declare(strict_types=1);

namespace App\Domain\Orders;

it('names the line and why it was refused', function (): void {
    $line = new BlockedLine('lst_00000000000000000000000001', 'Harbour at Dusk', UnavailableReason::SoldOut);

    expect($line->listingId)->toBe('lst_00000000000000000000000001')
        ->and($line->title)->toBe('Harbour at Dusk')
        ->and($line->reason)->toBe(UnavailableReason::SoldOut)
        ->and($line->lineId)->toBeNull();
});

it('carries the cart or order item id it came from, when it has one', function (): void {
    $line = new BlockedLine('lst_00000000000000000000000001', 'Harbour at Dusk', UnavailableReason::SoldOut, 'cti_00000000000000000000000001');

    expect($line->lineId)->toBe('cti_00000000000000000000000001');
});
