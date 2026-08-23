<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Escrow\LedgerEntryType;

it('reads its amount as money', function (): void {
    $payout = Payout::create([
        'seller_id' => $this->seller()->id,
        'period_start' => '2026-08-10',
        'period_end' => '2026-08-16',
        'amount_cents' => 9000,
        'paid_at' => '2026-08-17 00:00:00',
    ]);

    expect($payout->amount())->toBeMoney(9000);
});

it('reads the seller it belongs to and the ledger entries it settled', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $payout = Payout::create([
        'seller_id' => $fulfillment->seller_id,
        'period_start' => '2026-08-10',
        'period_end' => '2026-08-16',
        'amount_cents' => 9000,
        'paid_at' => '2026-08-17 00:00:00',
    ]);
    LedgerEntry::where('fulfillment_id', $fulfillment->id)
        ->where('type', LedgerEntryType::Released)
        ->update(['payout_id' => $payout->id]);

    expect($payout->seller->is($fulfillment->seller))->toBeTrue()
        ->and($payout->ledgerEntries()->count())->toBe(1);
});
