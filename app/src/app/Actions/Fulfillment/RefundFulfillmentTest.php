<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\FulfillmentEvent;
use App\Models\LedgerEntry;
use App\Models\Refund;
use Tests\CapturedStory;

it('refunds a delivered fulfillment as a dispute outcome', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller());

    $refunded = app(RefundFulfillment::class)($fulfillment, $this->admin(), 'The frame arrived broken.', $this->moment('2026-08-23 09:00:00'));

    expect($refunded->status)->toBe(FulfillmentStatus::Refunded)
        ->and($fulfillment->order->fresh()?->status)->toBe(OrderStatus::Refunded);
});

it('refunds a shipped fulfillment', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller());

    expect(app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Lost in transit.', $this->moment('2026-08-23 09:00:00'))->status)
        ->toBe(FulfillmentStatus::Refunded);
});

it('refunds an awaiting fulfillment whose seller never answered', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());

    expect(app(RefundFulfillment::class)($fulfillment, $this->admin(), 'The seller never answered.', $this->moment('2026-08-23 09:00:00'))->status)
        ->toBe(FulfillmentStatus::Refunded);
});

it('names the admin who issued it', function (): void {
    $admin = $this->admin();
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    app(RefundFulfillment::class)($fulfillment, $admin, 'The frame arrived broken.', $this->moment('2026-08-23 09:00:00'));

    $refund = Refund::sole();

    expect($refund->amount_cents)->toBe(10000)
        ->and($refund->issuer())->toBe(ActorType::Admin)
        ->and($refund->issued_by_id)->toBe($admin->id)
        ->and($refund->reason)->toBe('The frame arrived broken.');
});

it('restores no stock', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['quantity' => 1, 'price_cents' => 10000]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    app(RefundFulfillment::class)($order->fulfillments()->sole(), $this->admin(), 'Dispute.', $this->moment('2026-08-23 09:00:00'));

    expect($listing->refresh()->quantity)->toBe(0)
        ->and($listing->status)->toBe(ListingStatus::Sold);
});

it('drops the available balance when the money had already been released', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor($seller, priceCents: 10000);

    expect($seller->escrowBalance()->available->cents)->toBe(9000);

    app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Dispute.', $this->moment('2026-08-23 09:00:00'));

    expect($seller->escrowBalance()->available->cents)->toBe(0)
        ->and($seller->escrowBalance()->held->cents)->toBe(0)
        ->and(LedgerEntry::where('type', LedgerEntryType::Refunded)->sole()->amount_cents)->toBe(-9000);
});

it('refuses a second refund', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller());
    app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Dispute.', $this->moment('2026-08-23 09:00:00'));

    expect(fn () => app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Again.', $this->moment('2026-08-23 10:00:00')))
        ->toThrow(DomainRuleViolation::class, 'refunded to refunded');

    expect(Refund::count())->toBe(1);
});

it('refuses to refund a fulfillment already declined', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect(fn () => app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Dispute.', $this->moment('2026-08-23 09:00:00')))
        ->toThrow(DomainRuleViolation::class, 'declined to refunded');
});

it('refuses to refund a fulfillment on an order nobody has paid for', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $fulfillment = $order->fulfillments()->sole();

    expect(fn () => app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Dispute.', $this->moment('2026-08-23 09:00:00')))
        ->toThrow(DomainRuleViolation::class, 'has nothing to refund');

    expect($fulfillment->fresh()?->status)->toBe(FulfillmentStatus::AwaitingShipment)
        ->and(Refund::count())->toBe(0);
});

it('tells the story of the refund', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $log = CapturedStory::capture();

    app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Dispute.', $this->moment('2026-08-23 09:00:00'));

    expect($log->values('phase', 'refund.issue'))->toBe(['will', 'did'])
        ->and($log->line('refund.issue', 'did')['data'])->toMatchArray([
            'fulfillment_id' => $fulfillment->id,
            'amount_cents' => 10000,
            'status_to' => 'refunded',
            'reason' => 'Dispute.',
        ]);
});

it('appends the refunded event in the admin\'s name', function (): void {
    $admin = $this->admin();
    $fulfillment = $this->deliveredFulfillmentFor($this->seller());

    app(RefundFulfillment::class)($fulfillment, $admin, 'The frame arrived broken.', $this->moment('2026-08-23 09:00:00'));

    $event = FulfillmentEvent::where('fulfillment_id', $fulfillment->id)
        ->where('kind', FulfillmentEventKind::Refunded)
        ->sole();

    expect($event->actor_type)->toBe(ActorType::Admin)
        ->and($event->actor_id)->toBe($admin->id)
        ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-23 09:00:00')
        ->and($event->fulfillment_flow_step_id)->toBeNull();
});

it('appends nothing when the refund is refused', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller());
    app(RefundFulfillment::class)($fulfillment, $this->admin(), 'The frame arrived broken.', $this->moment('2026-08-23 09:00:00'));

    expect(fn () => app(RefundFulfillment::class)($fulfillment->refresh(), $this->admin(), 'Again.', $this->moment('2026-08-24 09:00:00')))
        ->toThrow(DomainRuleViolation::class);

    expect(FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->where('kind', FulfillmentEventKind::Refunded)->count())->toBe(1);
});
