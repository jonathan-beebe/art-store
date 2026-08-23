<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CartTotalsTest extends TestCase
{
    public function test_an_empty_cart_totals_nothing(): void
    {
        $totals = CartTotals::from([]);

        $this->assertSame(0, $totals->itemCount);
        $this->assertSame(0, $totals->subtotal->cents);
        $this->assertSame([], $totals->subtotalsBySeller());
    }

    public function test_it_counts_every_item_across_the_lines(): void
    {
        $totals = CartTotals::from([
            new CartLine(1, Money::fromCents(4500), 2),
            new CartLine(2, Money::fromCents(1000), 1),
        ]);

        $this->assertSame(3, $totals->itemCount);
    }

    public function test_it_adds_the_line_totals_into_a_subtotal(): void
    {
        $totals = CartTotals::from([
            new CartLine(1, Money::fromCents(4500), 2),
            new CartLine(2, Money::fromCents(1000), 1),
        ]);

        $this->assertSame(10000, $totals->subtotal->cents);
    }

    public function test_it_groups_the_subtotal_by_seller(): void
    {
        $totals = CartTotals::from([
            new CartLine(2, Money::fromCents(1000), 1),
            new CartLine(1, Money::fromCents(4500), 2),
            new CartLine(2, Money::fromCents(2500), 1),
        ]);

        $this->assertSame([1 => 9000, 2 => 3500], array_map(
            fn (Money $subtotal): int => $subtotal->cents,
            $totals->subtotalsBySeller(),
        ));
    }

    public function test_a_checkout_needs_at_least_one_line(): void
    {
        $this->expectException(DomainException::class);

        CartTotals::forCheckout([]);
    }

    public function test_a_checkout_totals_the_lines_it_is_given(): void
    {
        $totals = CartTotals::forCheckout([new CartLine(1, Money::fromCents(4500), 2)]);

        $this->assertSame(9000, $totals->subtotal->cents);
    }
}
