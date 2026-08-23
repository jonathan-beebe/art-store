<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

it('reads the cookie value off the request', function (): void {
    $request = Request::create('/', 'GET', [], [CustomerIdentity::COOKIE => '42']);

    expect(CustomerIdentity::cookieValue($request))->toBe('42');
});

it('reads no cookie value when the request carries none', function (): void {
    expect(CustomerIdentity::cookieValue(Request::create('/')))->toBeNull();
});

it('queues the customer id in the cookie', function (): void {
    $customer = $this->anonymousCustomer();

    CustomerIdentity::rememberInCookie($customer);

    expect(Cookie::queued(CustomerIdentity::COOKIE)?->getValue())->toBe((string) $customer->id);
});

it('queues the cookie forgotten', function (): void {
    CustomerIdentity::forgetCookie();

    $queued = Cookie::queued(CustomerIdentity::COOKIE);

    expect($queued)->not->toBeNull()
        ->and($queued?->getExpiresTime())->toBeLessThan(now()->getTimestamp());
});

it('attaches a customer to the request and reads it back as the current one', function (): void {
    $customer = $this->anonymousCustomer();
    $request = Request::create('/');
    app()->instance('request', $request);

    CustomerIdentity::attachTo($request, $customer);

    expect(CustomerIdentity::current()?->is($customer))->toBeTrue();
});

it('has no current customer when nothing was attached', function (): void {
    app()->instance('request', Request::create('/'));

    expect(CustomerIdentity::current())->toBeNull();
});
