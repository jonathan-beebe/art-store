<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\Customer;
use App\Models\Seller;
use App\Support\CustomerIdentity;

it('signs a seller out and returns them to the login page', function (): void {
    $this->actingAs(Seller::factory()->create(), 'seller');

    $response = $this->post('/seller/logout');

    $response->assertRedirect(route('auth.seller.login'));
    $this->assertGuest('seller');
});

it('signs a customer out and returns them to the storefront', function (): void {
    $this->actingAs(Customer::factory()->create(), 'customer');

    $response = $this->post('/logout');

    $response->assertRedirect(route('shop.home'));
    $this->assertGuest('customer');
});

it('drops the identity cookie when a customer signs out', function (): void {
    $customer = Customer::factory()->create();
    $this->actingAs($customer, 'customer');

    $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id)->post('/logout');

    $response->assertCookieExpired(CustomerIdentity::COOKIE);
});
