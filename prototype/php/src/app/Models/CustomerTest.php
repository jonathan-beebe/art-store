<?php

declare(strict_types=1);

namespace App\Models;

use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function test_a_customer_without_an_address_is_anonymous(): void
    {
        $this->assertTrue((new Customer)->isAnonymous());
    }

    public function test_a_customer_with_an_address_is_not_anonymous(): void
    {
        $customer = new Customer(['email' => 'shopper@example.com']);

        $this->assertFalse($customer->isAnonymous());
    }
}
