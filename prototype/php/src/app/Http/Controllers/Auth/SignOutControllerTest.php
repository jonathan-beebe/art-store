<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\Customer;
use App\Models\Seller;
use App\Support\CustomerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SignOutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_signs_a_seller_out_and_returns_them_to_the_login_page(): void
    {
        $this->actingAs(Seller::factory()->create(), 'seller');

        $response = $this->post('/seller/logout');

        $response->assertRedirect(route('auth.seller.login'));
        $this->assertGuest('seller');
    }

    public function test_it_signs_a_customer_out_and_returns_them_to_the_storefront(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $response = $this->post('/logout');

        $response->assertRedirect(route('shop.home'));
        $this->assertGuest('customer');
    }

    public function test_signing_a_customer_out_drops_the_identity_cookie(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id)->post('/logout');

        $response->assertCookieExpired(CustomerIdentity::COOKIE);
    }
}
