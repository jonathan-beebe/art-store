<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Support\CustomerIdentity;
use Illuminate\Support\Facades\Route;

it('gives a first visit an anonymous customer and a cookie', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $customer = Customer::sole();
    expect($customer->isAnonymous())->toBeTrue();
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
});

it('reuses the same customer on a second visit', function (): void {
    $this->get('/');
    $customer = Customer::sole();

    $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id)->get('/');

    expect(Customer::count())->toBe(1);
});

it('starts over when the cookie points at a deleted customer', function (): void {
    $response = $this->withCookie(CustomerIdentity::COOKIE, '9999')->get('/');

    $customer = Customer::sole();
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
});

it('starts over when the cookie holds junk', function (): void {
    $response = $this->withCookie(CustomerIdentity::COOKIE, 'not-an-id')->get('/');

    expect(Customer::count())->toBe(1);
    $response->assertCookie(CustomerIdentity::COOKIE, (string) Customer::sole()->id);
});

it('resolves a stale cookie through a recorded merge', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    CustomerMerge::create([
        'anonymous_customer_id' => $anonymous->id,
        'customer_id' => $verified->id,
    ]);

    $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)->get('/');

    $response->assertCookie(CustomerIdentity::COOKIE, (string) $verified->id);
    expect(Customer::count())->toBe(2);
});

it('treats an unencrypted customer_id cookie as a new anonymous visitor', function (): void {
    // EncryptCookies is the only reason a raw integer in this cookie is safe;
    // an unencrypted value fails decryption and reaches the middleware as if
    // no cookie were sent at all, even when it names a real customer.
    $existing = Customer::factory()->create();

    $response = $this->withUnencryptedCookie(CustomerIdentity::COOKIE, (string) $existing->id)->get('/');

    $visitor = Customer::where('id', '!=', $existing->id)->sole();
    expect($visitor->isAnonymous())->toBeTrue();
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $visitor->id);
});

it('does not resolve a forged cookie to the customer id it names', function (): void {
    $victim = Customer::factory()->create();

    $this->withUnencryptedCookie(CustomerIdentity::COOKIE, (string) $victim->id)->get('/');

    expect(Customer::count())->toBe(2);
});

it('lets a signed in customer outrank the cookie', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();
    $signedIn = Customer::factory()->create();

    $response = $this->actingAs($signedIn, 'customer')
        ->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)
        ->get('/');

    $response->assertCookie(CustomerIdentity::COOKIE, (string) $signedIn->id);
});

it('exposes the resolved customer to the customer helper', function (): void {
    Route::middleware(['web', 'customer.identity'])
        ->get('/customer-helper-probe', fn () => 'customer:'.customer()?->id);

    $response = $this->get('/customer-helper-probe');

    $response->assertSee('customer:'.Customer::sole()->id);
});
