<?php

declare(strict_types=1);

namespace App\Actions\Escrow;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Escrow\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Payout;

it('pays a seller whose delivery landed inside the period', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor($seller, priceCents: 45000, deliveredAt: $this->moment('2026-08-21 11:00:00'));

    $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

    expect($payouts)->toHaveCount(1);

    $payout = $payouts[0];
    expect($payout->seller_id)->toBe($seller->id)
        ->and($payout->amount_cents)->toBe(40500)
        ->and($payout->period_start->format('Y-m-d'))->toBe('2026-08-17')
        ->and($payout->period_end->format('Y-m-d'))->toBe('2026-08-23');
});

it('writes a matching paid-out ledger entry inside the period', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor($seller, priceCents: 45000, deliveredAt: $this->moment('2026-08-21 11:00:00'));

    $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

    $entry = LedgerEntry::query()->where('type', LedgerEntryType::PaidOut)->sole();
    expect($entry->amount_cents)->toBe(-40500)
        ->and($entry->payout_id)->toBe($payouts[0]->id)
        ->and($entry->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-23 23:59:59');
});

it('pays the same period once even when run twice', function (): void {
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 45000, deliveredAt: $this->moment('2026-08-21 11:00:00'));
    $runWeeklyPayout = app(RunWeeklyPayout::class);
    $runWeeklyPayout($this->moment('2026-08-24 09:00:00'));

    $second = $runWeeklyPayout($this->moment('2026-08-25 09:00:00'));

    expect($second)->toBe([])
        ->and(Payout::query()->count())->toBe(1);
});

it('pays each seller their own released amount', function (): void {
    $first = $this->seller('Blue Kiln Studio');
    $second = $this->seller('Rye Press');
    $this->deliveredFulfillmentFor($first, priceCents: 45000, deliveredAt: $this->moment('2026-08-21 11:00:00'));
    $this->deliveredFulfillmentFor($second, priceCents: 10000, deliveredAt: $this->moment('2026-08-21 12:00:00'));

    app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

    expect(Payout::query()->orderBy('seller_id')->pluck('amount_cents', 'seller_id')->all())
        ->toBe([$first->id => 40500, $second->id => 9000]);
});

it('does not pay out money still held in escrow', function (): void {
    $seller = $this->seller();
    app(FinalizeOrder::class)(
        $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000])),
        '4242 4242 4242 4242',
        $this->moment('2026-08-20 10:00:00'),
    );

    $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

    expect($payouts)->toBe([])
        ->and(Payout::query()->count())->toBe(0);
});

it('waits for the next run when a delivery lands after the period ends', function (): void {
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 45000, deliveredAt: $this->moment('2026-08-24 11:00:00'));

    $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 12:00:00'));

    expect($payouts)->toBe([]);
});
