<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PurchaserTest extends TestCase
{
    public function test_a_purchaser_with_a_verification_timestamp_is_verified(): void
    {
        $purchaser = new Purchaser(7, 'buyer@example.test', new DateTimeImmutable('2026-08-22 10:00:00'));

        $this->assertTrue($purchaser->isEmailVerified());
    }

    public function test_a_purchaser_without_a_verification_timestamp_is_unverified(): void
    {
        $this->assertFalse((new Purchaser(7, 'buyer@example.test', null))->isEmailVerified());
    }
}
