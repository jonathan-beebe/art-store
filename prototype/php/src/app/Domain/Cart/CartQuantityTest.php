<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CartQuantityTest extends TestCase
{
    public function test_it_keeps_a_quantity_the_listing_can_cover(): void
    {
        $this->assertSame(2, CartQuantity::withinStock(2, 5));
    }

    public function test_it_caps_a_quantity_at_the_stock_on_hand(): void
    {
        $this->assertSame(5, CartQuantity::withinStock(9, 5));
    }

    public function test_it_rejects_a_listing_with_nothing_left(): void
    {
        $this->expectException(DomainException::class);

        CartQuantity::withinStock(1, 0);
    }

    public function test_it_rejects_a_request_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CartQuantity::withinStock(0, 5);
    }
}
