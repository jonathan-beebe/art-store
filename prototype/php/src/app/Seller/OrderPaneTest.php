<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\LaneFilter;

$row = fn (string $id): OrderRow => new OrderRow(
    id: $id,
    href: "/seller/orders/{$id}",
    selected: false,
    buyer: 'Luna Lovegood',
    itemLabel: 'Harbour at Dusk',
    subtotal: '$450.00',
    statusLabel: 'Awaiting shipment',
    tint: 'yellow',
    placed: 'Aug 20',
);

it('counts the rows it shows against the rows the lane holds', function () use ($row): void {
    $pane = new OrderPane(LaneFilter::ToShip, [$row('ful_01'), $row('ful_02')], total: 7);

    expect($pane->shown())->toBe(2)
        ->and($pane->total)->toBe(7)
        ->and($pane->isEmpty())->toBeFalse();
});

it('reads as empty when the lane holds nothing', function (): void {
    $pane = new OrderPane(LaneFilter::Done, [], total: 0);

    expect($pane->isEmpty())->toBeTrue()
        ->and($pane->shown())->toBe(0);
});
