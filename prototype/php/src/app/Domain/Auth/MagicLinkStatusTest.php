<?php

namespace App\Domain\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MagicLinkStatusTest extends TestCase
{
    public function test_a_fresh_unconsumed_link_is_usable(): void
    {
        $status = MagicLinkStatus::of(
            new DateTimeImmutable('2026-08-22 12:15:00'),
            null,
            new DateTimeImmutable('2026-08-22 12:00:00'),
        );

        $this->assertSame(MagicLinkStatus::Usable, $status);
    }

    public function test_a_link_is_expired_once_now_reaches_the_expiry(): void
    {
        $status = MagicLinkStatus::of(
            new DateTimeImmutable('2026-08-22 12:15:00'),
            null,
            new DateTimeImmutable('2026-08-22 12:15:00'),
        );

        $this->assertSame(MagicLinkStatus::Expired, $status);
    }

    public function test_a_link_is_expired_after_the_expiry(): void
    {
        $status = MagicLinkStatus::of(
            new DateTimeImmutable('2026-08-22 12:15:00'),
            null,
            new DateTimeImmutable('2026-08-22 12:15:01'),
        );

        $this->assertSame(MagicLinkStatus::Expired, $status);
    }

    public function test_a_consumed_link_is_consumed(): void
    {
        $status = MagicLinkStatus::of(
            new DateTimeImmutable('2026-08-22 12:15:00'),
            new DateTimeImmutable('2026-08-22 12:05:00'),
            new DateTimeImmutable('2026-08-22 12:06:00'),
        );

        $this->assertSame(MagicLinkStatus::Consumed, $status);
    }

    public function test_consumption_outranks_expiry(): void
    {
        $status = MagicLinkStatus::of(
            new DateTimeImmutable('2026-08-22 12:15:00'),
            new DateTimeImmutable('2026-08-22 12:05:00'),
            new DateTimeImmutable('2026-08-22 13:00:00'),
        );

        $this->assertSame(MagicLinkStatus::Consumed, $status);
    }
}
