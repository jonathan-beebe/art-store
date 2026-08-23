<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Customer;
use App\Support\CustomerIdentity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

it('claims the address and logs the resulting customer in on the customer guard', function (): void {
    $customer = app(SignInCustomer::class)('shopper@example.com', null, $this->moment('2026-08-20 09:00:00'));

    expect(Auth::guard('customer')->id())->toBe($customer->id)
        ->and(Customer::sole()->is($customer))->toBeTrue();
});

it('remembers the resulting customer in the identity cookie', function (): void {
    $customer = app(SignInCustomer::class)('shopper@example.com', null, $this->moment('2026-08-20 09:00:00'));

    expect(Cookie::hasQueued(CustomerIdentity::COOKIE))->toBeTrue()
        ->and(Cookie::queued(CustomerIdentity::COOKIE)?->getValue())->toBe((string) $customer->id);
});

it('claims the anonymous row the cookie pointed at', function (): void {
    $anonymous = Customer::factory()->anonymous()->create();

    $customer = app(SignInCustomer::class)('shopper@example.com', $anonymous, $this->moment('2026-08-20 09:00:00'));

    expect($customer->is($anonymous))->toBeTrue()
        ->and($customer->email)->toBe('shopper@example.com');
});
