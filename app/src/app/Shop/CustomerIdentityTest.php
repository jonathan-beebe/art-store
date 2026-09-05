<?php

declare(strict_types=1);

namespace App\Shop;

use App\Actions\Customers\ResolveCustomerFromCookie;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsVisit;
use App\Logging\RequestMarks;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

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

it('commits an unsaved customer: saves it, queues the cookie, and names the actor', function (): void {
    $visitor = new Customer;

    $committed = CustomerIdentity::commit($visitor);

    expect($committed->exists)->toBeTrue()
        ->and($committed->id)->not->toBeNull()
        ->and(Cookie::queued(CustomerIdentity::COOKIE)?->getValue())->toBe((string) $committed->id);
});

it('buffers a claim on the session\'s visit when it commits an unsaved customer', function (): void {
    $sessionId = 'ses_01J00000000000000000000ABC';
    $request = Request::create('/');
    $request->attributes->set(RequestMarks::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000XYZ');
    $request->cookies->set(RequestMarks::SESSION_COOKIE, $sessionId);
    app()->instance('request', $request);

    $committed = CustomerIdentity::commit(new Customer);

    $analytics = app(Analytics::class);
    $analytics->recordVisit(new AnalyticsVisit($sessionId, now()->toDateTimeImmutable(), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_visits')->sole()->actor_id)->toBe($committed->id);
});

it('claims no visit when the request carries no session id', function (): void {
    $request = Request::create('/');
    $request->attributes->set(RequestMarks::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000XYZ');
    app()->instance('request', $request);

    CustomerIdentity::commit(new Customer);

    expect(app(Analytics::class)->pending())->toBe(0);
});

it('returns an already saved customer unchanged, queuing no cookie', function (): void {
    $visitor = $this->anonymousCustomer();

    $committed = CustomerIdentity::commit($visitor);

    expect($committed)->toBe($visitor)
        ->and(Cookie::queued(CustomerIdentity::COOKIE))->toBeNull();
});

it('resolves the cookie once and answers every later ask from the request', function (): void {
    $customer = $this->anonymousCustomer();
    $request = Request::create('/', 'GET', [], [CustomerIdentity::COOKIE => (string) $customer->id]);
    $resolve = app(ResolveCustomerFromCookie::class);

    $first = CustomerIdentity::fromCookie($request, $resolve);
    // Whatever the second ask reads, it is not the database: the row the
    // cookie names is gone by the time it is made.
    $customer->delete();
    $second = CustomerIdentity::fromCookie($request, $resolve);

    expect($first?->is($customer))->toBeTrue()
        ->and($second)->toBe($first);
});
