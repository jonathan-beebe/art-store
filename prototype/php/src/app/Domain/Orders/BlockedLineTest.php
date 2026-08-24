<?php

declare(strict_types=1);

namespace App\Domain\Orders;

it('names the line and why it was refused', function (): void {
    $line = new BlockedLine('lst_00000000000000000000000001', 'Harbour at Dusk', UnavailableReason::SoldOut);

    expect($line->listingId)->toBe('lst_00000000000000000000000001')
        ->and($line->title)->toBe('Harbour at Dusk')
        ->and($line->reason)->toBe(UnavailableReason::SoldOut);
});
