<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class FeeTest extends TestCase
{
    public function test_the_platform_takes_a_tenth_of_the_subtotal(): void
    {
        $this->assertSame(1000, Fee::platform(Money::fromCents(10000))->cents);
    }

    public function test_a_half_cent_fee_rounds_up(): void
    {
        $this->assertSame(1, Fee::platform(Money::fromCents(5))->cents);
    }

    public function test_the_seller_nets_the_subtotal_less_the_fee(): void
    {
        $this->assertSame(9000, Fee::net(Money::fromCents(10000))->cents);
    }

    public function test_the_fee_and_the_net_add_back_up_to_the_subtotal(): void
    {
        $subtotal = Money::fromCents(4599);

        $this->assertSame(4599, Fee::platform($subtotal)->add(Fee::net($subtotal))->cents);
    }
}
