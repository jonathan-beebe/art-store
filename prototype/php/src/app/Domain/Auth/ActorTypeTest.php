<?php

namespace App\Domain\Auth;

use PHPUnit\Framework\TestCase;

final class ActorTypeTest extends TestCase
{
    public function test_each_actor_names_its_own_guard(): void
    {
        $this->assertSame('seller', ActorType::Seller->guard());
        $this->assertSame('customer', ActorType::Customer->guard());
    }

    public function test_each_actor_lands_on_its_own_site(): void
    {
        $this->assertSame('seller.dashboard', ActorType::Seller->homeRouteName());
        $this->assertSame('shop.account', ActorType::Customer->homeRouteName());
    }

    public function test_each_actor_signs_in_on_its_own_site(): void
    {
        $this->assertSame('auth.seller.login', ActorType::Seller->loginRouteName());
        $this->assertSame('auth.customer.login', ActorType::Customer->loginRouteName());
    }
}
