<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Fulfillment\MarkShipped;

it('includes only the item whose own seller still has a fulfillment awaiting shipment', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $shippedFulfillment = $order->fulfillments()->firstOrFail();
    app(MarkShipped::class)($shippedFulfillment, 'USPS', 'TRACK1', $this->moment('2026-08-21 09:00:00'));

    $stillAwaitingItem = $order->items()->where('seller_id', '!=', $shippedFulfillment->seller_id)->sole();

    expect(OrderItem::query()->awaitingShipment()->pluck('id')->all())->toBe([$stillAwaitingItem->id]);
});

it('reads the price it was bought at as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    expect($order->items()->sole()->unitPrice())->toBeMoney(45000);
});

it('multiplies the unit price out over the quantity for a legacy line', function (): void {
    $item = new OrderItem(['unit_price_cents' => 45000, 'quantity' => 3]);

    expect($item->hasVariant())->toBeFalse()
        ->and($item->lineTotal())->toBeMoney(135000);
});

it('reads a configured line total off its frozen breakdown, not unit price times quantity', function (): void {
    $item = OrderItem::factory()->configured()->create(['unit_price_cents' => 6400, 'quantity' => 1]);

    expect($item->hasVariant())->toBeTrue()
        ->and($item->lineTotal())->toBeMoney(12800);
});

it('reconstructs its frozen breakdown into labeled money lines', function (): void {
    $item = OrderItem::factory()->configured()->create();

    $lines = $item->priceBreakdown()->lines;

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->label)->toBe('Base price')
        ->and($lines[0]->amount)->toBeMoney(12000)
        ->and($lines[1]->label)->toBe('Rose Gold')
        ->and($lines[1]->amount)->toBeMoney(800);
});

it('reads an empty breakdown for a legacy line that never froze one', function (): void {
    $item = new OrderItem(['unit_price_cents' => 45000, 'quantity' => 1]);

    expect($item->priceBreakdown()->lines)->toBe([]);
});
