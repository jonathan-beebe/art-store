<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CartLineTest extends TestCase
{
    public function test_a_line_totals_its_unit_price_across_the_quantity(): void
    {
        $line = new CartLine(1, Money::fromCents(4500), 3);

        $this->assertSame(13500, $line->total()->cents);
    }

    public function test_a_line_needs_at_least_one_item(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CartLine(1, Money::fromCents(4500), 0);
    }
}
