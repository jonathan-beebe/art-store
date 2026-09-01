<?php

declare(strict_types=1);

namespace App\Actions\Escrow;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Refund;
use Illuminate\Database\QueryException;

it('writes the refund row, the reversing entry, and the order total together', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller(), priceCents: 10000);

    $refund = app(IssueRefund::class)(
        $fulfillment,
        ActorType::Admin,
        $this->admin()->id,
        'The piece never arrived.',
        $this->moment('2026-08-23 09:00:00'),
    );

    expect($refund->id)->toStartWith('rfd_')
        ->and($refund->amount_cents)->toBe(10000)
        ->and($refund->fulfillment_id)->toBe($fulfillment->id)
        ->and(LedgerEntry::where('type', LedgerEntryType::Refunded)->sole()->amount_cents)->toBe(-9000)
        ->and($fulfillment->order->fresh()?->refunded_cents)->toBe(10000);
});

it('names the approved payment it reverses', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());

    $refund = app(IssueRefund::class)(
        $fulfillment,
        ActorType::Seller,
        $fulfillment->seller_id,
        'Damaged.',
        $this->moment('2026-08-23 09:00:00'),
    );

    expect($refund->payment_id)->toBe($fulfillment->order->payments()->sole()->id);
});

it('collides on the refunds table\'s unique fulfillment_id constraint when called twice for the same fulfillment', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller(), priceCents: 10000);
    app(IssueRefund::class)($fulfillment, ActorType::Admin, $this->admin()->id, 'First refund.', $this->moment('2026-08-23 09:00:00'));

    expect(fn () => app(IssueRefund::class)($fulfillment, ActorType::Admin, $this->admin()->id, 'Second refund.', $this->moment('2026-08-23 09:05:00')))
        ->toThrow(QueryException::class);

    expect(Refund::count())->toBe(1);
});

it('refuses an order no card ever cleared', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    expect(fn () => app(IssueRefund::class)(
        $order->fulfillments()->sole(),
        ActorType::Admin,
        $this->admin()->id,
        'Dispute.',
        $this->moment('2026-08-23 09:00:00'),
    ))->toThrow(DomainRuleViolation::class, 'has nothing to refund');

    expect(Refund::count())->toBe(0);
});
