<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\LedgerEntry;
use DomainException;

it('records when the parcel arrived', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), priceCents: 45000);

    $fulfillment = app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-23 14:00:00'));

    expect($fulfillment->status)->toBe(FulfillmentStatus::Delivered)
        ->and($fulfillment->delivered_at?->format('Y-m-d H:i:s'))->toBe('2026-08-23 14:00:00');
});

it('releases the escrow hold on delivery', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), priceCents: 45000);

    app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-23 14:00:00'));

    $entry = LedgerEntry::query()->where('type', LedgerEntryType::Released)->sole();
    expect($entry->amount_cents)->toBe(40500)
        ->and($entry->seller_id)->toBe($fulfillment->seller_id)
        ->and($entry->fulfillment_id)->toBe($fulfillment->id)
        ->and($entry->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-23 14:00:00');
});

it('delivers the order on its last delivery', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), priceCents: 45000);

    app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-23 14:00:00'));

    expect($fulfillment->order)->toHaveStatus(OrderStatus::Delivered);
});

it('releases the escrow once when delivery is confirmed twice', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), priceCents: 45000);
    $confirmDelivered = app(ConfirmDelivered::class);
    $confirmDelivered($fulfillment, $this->moment('2026-08-23 14:00:00'));

    expect(fn () => $confirmDelivered($fulfillment->refresh(), $this->moment('2026-08-24 14:00:00')))
        ->toThrow(DomainRuleViolation::class, 'A fulfillment cannot move from delivered to delivered.');

    expect(LedgerEntry::query()->where('type', LedgerEntryType::Released)->count())->toBe(1)
        ->and($fulfillment->refresh()->delivered_at?->format('Y-m-d H:i:s'))->toBe('2026-08-23 14:00:00');
});

it('refuses a fulfillment that never shipped', function (): void {
    $order = app(FinalizeOrder::class)(
        $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000])),
        '4242 4242 4242 4242',
        $this->moment('2026-08-20 10:00:00'),
    );

    app(ConfirmDelivered::class)($order->fulfillments()->sole(), $this->moment('2026-08-23 14:00:00'));
})->throws(DomainException::class);
