<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

it('names the three stages money passes through', function (): void {
    expect(array_column(LedgerEntryType::cases(), 'value'))
        ->toBe(['held', 'released', 'paid_out']);
});
