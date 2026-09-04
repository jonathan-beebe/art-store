<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Seller\HeldState;

it('totals from the ledger fold and lists every order still holding money, oldest first', function (): void {
    $seller = $this->seller();
    $first = $this->shippedFulfillmentFor($seller, priceCents: 10_000, orderedAt: $this->moment('2026-08-20 10:00:00'), shippedAt: $this->moment('2026-08-21 09:00:00'));
    $second = $this->paidFulfillmentFor($seller, priceCents: 5_000, paidAt: $this->moment('2026-08-21 10:00:00'));

    $held = HeldEscrow::for($seller);

    expect($held->total->equals($seller->refresh()->escrowBalance()->held))->toBeTrue()
        ->and($held->total->format())->toBe('$135.00')
        ->and($held->orders)->toHaveCount(2)
        ->and($held->orders[0]->fulfillmentId)->toBe($first->id)
        ->and($held->orders[1]->fulfillmentId)->toBe($second->id);
});

it('reads an unshipped order as not yet shipped and a shipped one as in transit since it shipped', function (): void {
    $seller = $this->seller();
    $unshipped = $this->paidFulfillmentFor($seller);
    $shipped = $this->shippedFulfillmentFor($seller, shippedAt: $this->moment('2026-08-21 09:00:00'));

    $held = HeldEscrow::for($seller);
    $unshippedRow = collect($held->orders)->sole(fn ($order): bool => $order->fulfillmentId === $unshipped->id);
    $shippedRow = collect($held->orders)->sole(fn ($order): bool => $order->fulfillmentId === $shipped->id);

    expect($unshippedRow->state)->toBe(HeldState::NotYetShipped)
        ->and($unshippedRow->shippedAt)->toBeNull()
        ->and($shippedRow->state)->toBe(HeldState::InTransit)
        ->and($shippedRow->shippedAt?->format('Y-m-d H:i:s'))->toBe('2026-08-21 09:00:00');
});

it('leaves out delivered, declined, and refunded fulfillments', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor($seller);

    $held = HeldEscrow::for($seller);

    expect($held->orders)->toBeEmpty()
        ->and($held->total->isZero())->toBeTrue();
});

it('reports each order buyer, item, and net', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor(
        $this->verifiedCustomer(),
        $this->listing($seller, ['title' => 'Nine Owls', 'price_cents' => 10_000]),
    );
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();

    $held = HeldEscrow::for($seller);

    expect($held->orders[0]->buyerName)->toBe($order->refresh()->shipping_name)
        ->and($held->orders[0]->itemLabel)->toBe('Nine Owls')
        ->and($held->orders[0]->net->format())->toBe('$90.00')
        ->and($held->orders[0]->fulfillmentId)->toBe($fulfillment->id);
});
