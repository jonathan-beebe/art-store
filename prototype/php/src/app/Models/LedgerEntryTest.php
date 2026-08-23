<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Escrow\LedgerEntryType;

it('reads its amount as money and reports the movement it made', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $entry = $fulfillment->ledgerEntries()->where('type', LedgerEntryType::Held)->sole();

    expect($entry->amount())->toBeMoney(9000)
        ->and($entry->toMovement()->type)->toBe(LedgerEntryType::Held)
        ->and($entry->toMovement()->amount)->toBeMoney(9000);
});

it('narrows to the entries settled by a moment', function (): void {
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    expect(LedgerEntry::query()->occurredBy($this->moment('2026-08-21 00:00:00'))->count())->toBe(1)
        ->and(LedgerEntry::query()->occurredBy($this->moment('2026-08-23 00:00:00'))->count())->toBe(2);
});
