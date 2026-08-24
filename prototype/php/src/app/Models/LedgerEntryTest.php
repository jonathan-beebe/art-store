<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Escrow\RunWeeklyPayout;
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

it('reads the seller and fulfillment behind a held entry, with no payout yet', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor($seller, priceCents: 10000);
    $entry = $fulfillment->ledgerEntries()->where('type', LedgerEntryType::Held)->sole();

    expect($entry->seller->is($seller))->toBeTrue()
        ->and($entry->fulfillment()->sole()->is($fulfillment))->toBeTrue()
        ->and($entry->payout)->toBeNull();
});

it('reads the payout a paid-out entry settled through', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $payout = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'))[0];
    $paidOut = LedgerEntry::where('type', LedgerEntryType::PaidOut)->sole();

    expect($paidOut->payout()->sole()->is($payout))->toBeTrue()
        ->and($paidOut->fulfillment)->toBeNull();
});

it('sums the entries of each seller, fulfillment and type into one row apiece', function (): void {
    $seller = $this->seller();
    $first = $this->deliveredFulfillmentFor($seller, priceCents: 10000, trackingNumber: 'RM1');
    $this->deliveredFulfillmentFor($seller, priceCents: 20000, trackingNumber: 'RM2');

    $rows = LedgerEntry::query()->totalledByType()->get();
    $ofFirst = $rows->where('fulfillment_id', $first->id);

    expect($rows)->toHaveCount(4)
        ->and($ofFirst->firstWhere('type', LedgerEntryType::Held)?->amount())->toBeMoney(9000)
        ->and($ofFirst->firstWhere('type', LedgerEntryType::Released)?->amount())->toBeMoney(9000)
        ->and($rows->sum(fn (LedgerEntry $row): int => $row->amount_cents))->toBe(54000);
});

it('narrows to one seller\'s entries, leaving another\'s out', function (): void {
    $first = $this->seller('Blue Kiln Studio');
    $second = $this->seller('Rye Press');
    $this->deliveredFulfillmentFor($first, priceCents: 10000);
    $this->deliveredFulfillmentFor($second, priceCents: 20000);

    expect(LedgerEntry::query()->ofSeller($first->id)->get()->every(fn (LedgerEntry $entry): bool => $entry->seller_id === $first->id))
        ->toBeTrue()
        ->and(LedgerEntry::query()->ofSeller(null)->count())->toBe(4);
});

it('narrows to one entry type, leaving the others out', function (): void {
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    expect(LedgerEntry::query()->ofType(LedgerEntryType::Held)->get()->every(fn (LedgerEntry $entry): bool => $entry->type === LedgerEntryType::Held))
        ->toBeTrue()
        ->and(LedgerEntry::query()->ofType(null)->count())->toBe(2);
});

it('folds every seller\'s balance out of one read of the ledger', function (): void {
    $first = $this->seller('Blue Kiln Studio');
    $second = $this->seller('Rye Press');
    $this->deliveredFulfillmentFor($first, priceCents: 10000);
    $this->shippedFulfillmentFor($second, priceCents: 20000);
    $quiet = $this->seller('Quiet Press');

    $balances = LedgerEntry::balancesBySeller();

    expect($balances->of($first->id)->available)->toBeMoney(9000)
        ->and($balances->of($first->id)->held)->toBeMoney(0)
        ->and($balances->of($second->id)->held)->toBeMoney(18000)
        ->and($balances->of($second->id)->available)->toBeMoney(0)
        ->and($balances->of($quiet->id)->held)->toBeMoney(0);
});
