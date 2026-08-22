<?php

namespace App\Domain\Notifications;

use PHPUnit\Framework\TestCase;

final class RecipientTypeTest extends TestCase
{
    public function test_it_names_the_two_sides_of_the_marketplace(): void
    {
        $this->assertSame(['seller', 'customer'], array_column(RecipientType::cases(), 'value'));
    }
}
