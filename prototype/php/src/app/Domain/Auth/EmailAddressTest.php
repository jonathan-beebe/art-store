<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function test_it_lowercases_an_address(): void
    {
        $this->assertSame('artist@example.com', EmailAddress::normalize('Artist@Example.COM'));
    }

    public function test_it_trims_surrounding_whitespace(): void
    {
        $this->assertSame('artist@example.com', EmailAddress::normalize("  artist@example.com\n"));
    }

    public function test_it_leaves_an_already_normal_address_alone(): void
    {
        $this->assertSame('artist@example.com', EmailAddress::normalize('artist@example.com'));
    }
}
