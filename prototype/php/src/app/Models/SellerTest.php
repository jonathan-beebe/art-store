<?php

namespace App\Models;

use PHPUnit\Framework\TestCase;

final class SellerTest extends TestCase
{
    public function test_a_shop_name_is_what_the_seller_is_called(): void
    {
        $seller = new Seller([
            'email' => 'artist@example.com',
            'name' => 'Ada Painter',
            'shop_name' => 'Ada Studio',
        ]);

        $this->assertSame('Ada Studio', $seller->displayName());
    }

    public function test_a_seller_without_a_shop_name_falls_back_to_their_name(): void
    {
        $seller = new Seller(['email' => 'artist@example.com', 'name' => 'Ada Painter']);

        $this->assertSame('Ada Painter', $seller->displayName());
    }

    public function test_a_seller_who_has_given_nothing_but_an_address_is_called_by_it(): void
    {
        $seller = new Seller(['email' => 'artist@example.com']);

        $this->assertSame('artist@example.com', $seller->displayName());
    }
}
