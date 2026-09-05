<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Models\Listing;
use App\Shop\CustomerIdentity;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('gives a read-only visit no row and no cookie', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    expect(Customer::count())->toBe(0);
    $response->assertCookieMissing(CustomerIdentity::COOKIE);
});

it('mints a row and sets the cookie once a page tracks an event under it', function (): void {
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertOk();
    $customer = Customer::sole();
    expect($customer->isAnonymous())->toBeTrue();
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
});

it('reuses the same customer on a second visit once a row exists', function (): void {
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);
    $this->get('/art/harbour-at-dawn');
    $customer = Customer::sole();

    $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id)->get('/art/harbour-at-dawn');

    expect(Customer::count())->toBe(1);
});

it('starts over when the cookie points at a deleted customer', function (): void {
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);

    $response = $this->withCookie(CustomerIdentity::COOKIE, '9999')->get('/art/harbour-at-dawn');

    $customer = Customer::sole();
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
});

it('starts over when the cookie holds junk', function (): void {
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);

    $response = $this->withCookie(CustomerIdentity::COOKIE, 'not-an-id')->get('/art/harbour-at-dawn');

    expect(Customer::count())->toBe(1);
    $response->assertCookie(CustomerIdentity::COOKIE, (string) Customer::sole()->id);
});

it('resolves a stale cookie through a recorded merge', function (): void {
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    CustomerMerge::factory()->create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $verified->id]);

    $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)->get('/art/harbour-at-dawn');

    $response->assertCookie(CustomerIdentity::COOKIE, (string) $verified->id);
    expect(Customer::count())->toBe(2);
});

it('treats an unencrypted customer_id cookie as a new anonymous visitor', function (): void {
    // EncryptCookies is the only reason a raw integer in this cookie is safe;
    // an unencrypted value fails decryption and reaches the middleware as if
    // no cookie were sent at all, even when it names a real customer.
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);
    $existing = Customer::factory()->create();

    $response = $this->withUnencryptedCookie(CustomerIdentity::COOKIE, (string) $existing->id)->get('/art/harbour-at-dawn');

    $visitor = Customer::where('id', '!=', $existing->id)->sole();
    expect($visitor->isAnonymous())->toBeTrue();
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $visitor->id);
});

it('does not resolve a forged cookie to the customer id it names', function (): void {
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);
    $victim = Customer::factory()->create();

    $this->withUnencryptedCookie(CustomerIdentity::COOKIE, (string) $victim->id)->get('/art/harbour-at-dawn');

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

it('exposes the resolved customer through CustomerIdentity', function (): void {
    Route::middleware(['web', 'customer.identity'])
        ->get('/customer-identity-probe', fn () => 'customer:'.CustomerIdentity::current()?->id);
    $customer = Customer::factory()->anonymous()->create();

    $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id)->get('/customer-identity-probe');

    $response->assertSee('customer:'.$customer->id);
});

it('resolves the visitor from the cookie once for the whole request', function (string $cookieValue): void {
    $lookups = [];

    DB::listen(function (QueryExecuted $query) use (&$lookups): void {
        if (str_contains($query->sql, 'customer_merges')) {
            $lookups[] = $query->sql;
        }
    });

    $this->withCookie(CustomerIdentity::COOKIE, $cookieValue)->get('/')->assertOk();

    expect($lookups)->toHaveCount(1);
})->with([
    'a cookie naming a customer' => fn (): string => (string) Customer::factory()->anonymous()->create()->id,
    'a cookie naming no customer' => 'cus_01J00000000000000000000ABC',
]);

it('resolves the visitor for itself where nothing has named one yet', function (): void {
    $visitor = Customer::factory()->anonymous()->create();
    $request = Request::create('/probe', 'GET', [], [CustomerIdentity::COOKIE => (string) $visitor->id]);

    $response = app(ResolveCustomerIdentity::class)->handle($request, fn (): Response => new Response('probed'));

    expect($response->getContent())->toBe('probed')
        ->and(Customer::count())->toBe(1)
        ->and(Cookie::queued(CustomerIdentity::COOKIE)?->getValue())->toBe((string) $visitor->id);
});

it('attaches an unsaved customer for a read-only request with no cookie, and commits nothing', function (): void {
    $request = Request::create('/probe', 'GET');

    app(ResolveCustomerIdentity::class)->handle($request, fn (): Response => new Response('probed'));

    expect(Customer::count())->toBe(0)
        ->and(Cookie::queued(CustomerIdentity::COOKIE))->toBeNull();
});
