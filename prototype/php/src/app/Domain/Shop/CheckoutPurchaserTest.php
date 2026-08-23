<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CheckoutPurchaserTest extends TestCase
{
    public function test_a_guest_buys_under_the_address_they_typed(): void
    {
        $purchaser = CheckoutPurchaser::forCustomer(7, null, null, '  Guest@Example.COM ');

        $this->assertSame(7, $purchaser->customerId);
        $this->assertSame('guest@example.com', $purchaser->email);
        $this->assertFalse($purchaser->isEmailVerified());
    }

    public function test_a_verified_customer_buys_under_the_address_on_their_account(): void
    {
        $verifiedAt = new DateTimeImmutable('2026-08-20 10:00:00');

        $purchaser = CheckoutPurchaser::forCustomer(7, 'ada@example.com', $verifiedAt, 'someone-else@example.com');

        $this->assertSame('ada@example.com', $purchaser->email);
        $this->assertSame($verifiedAt, $purchaser->emailVerifiedAt);
        $this->assertTrue($purchaser->isEmailVerified());
    }
}
