<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

it('takes a tenth of the subtotal for the platform', function (): void {
    expect(Fee::platform(Money::fromCents(10000))->cents)->toBe(1000);
});

it('rounds a half-cent fee up', function (): void {
    expect(Fee::platform(Money::fromCents(5))->cents)->toBe(1);
});

it('nets the seller the subtotal less the fee', function (): void {
    expect(Fee::net(Money::fromCents(10000))->cents)->toBe(9000);
});

it('adds the fee and the net back up to the subtotal', function (): void {
    $subtotal = Money::fromCents(4599);

    expect(Fee::platform($subtotal)->add(Fee::net($subtotal))->cents)->toBe(4599);
});
