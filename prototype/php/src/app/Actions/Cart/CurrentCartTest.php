<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use Tests\CommerceTestCase;

final class CurrentCartTest extends CommerceTestCase
{
    public function test_a_customer_without_a_cart_gets_one(): void
    {
        $customer = $this->anonymousCustomer();

        $cart = app(CurrentCart::class)($customer);

        $this->assertSame($customer->id, $cart->customer_id);
        $this->assertSame(1, Cart::count());
    }

    public function test_it_returns_the_same_cart_twice(): void
    {
        $customer = $this->anonymousCustomer();

        $first = app(CurrentCart::class)($customer);
        $second = app(CurrentCart::class)($customer);

        $this->assertSame($first->id, $second->id);
    }

    public function test_after_a_merge_it_picks_the_cart_holding_the_items(): void
    {
        $customer = $this->verifiedCustomer();
        $this->cartFor($customer);
        $filled = $this->cartFor($customer);
        CartItem::create([
            'cart_id' => $filled->id,
            'listing_id' => $this->listing($this->seller())->id,
            'quantity' => 1,
        ]);

        $this->assertSame($filled->id, app(CurrentCart::class)($customer)->id);
    }
}
