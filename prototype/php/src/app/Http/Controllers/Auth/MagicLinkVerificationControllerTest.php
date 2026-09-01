<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\MagicLink;
use App\Models\Seller;
use App\Support\CustomerIdentity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\CapturedStory;

$flashedLink = fn (): string => Arr::string(Session::all(), 'debug_magic_link');

$sellerLinkFor = function (string $email) use ($flashedLink): string {
    test()->post('/seller/login', ['email' => $email]);

    return $flashedLink();
};

$adminLinkFor = function (string $email) use ($flashedLink): string {
    test()->post('/admin/login', ['email' => $email]);

    return $flashedLink();
};

$customerLinkFor = function (string $email, ?string $redirectTo = null) use ($flashedLink): string {
    test()->post('/login', array_filter(['email' => $email, 'redirect_to' => $redirectTo]));

    return $flashedLink();
};

it('gives a first-time seller an account and lands them on the dashboard', function () use ($sellerLinkFor): void {
    $response = $this->get($sellerLinkFor('artist@example.com'));

    $response->assertRedirect(route('seller.dashboard'));
    $this->assertAuthenticated('seller');
    $seller = Seller::sole();
    expect($seller->email)->toBe('artist@example.com')
        ->and($seller->email_verified_at)->not->toBeNull();
});

it('signs a returning seller in to the same account', function () use ($sellerLinkFor): void {
    $seller = Seller::factory()->create(['email' => 'artist@example.com']);

    $this->get($sellerLinkFor('Artist@Example.com'));

    expect(Seller::count())->toBe(1);
    $this->assertAuthenticatedAs($seller->refresh(), 'seller');
});

it('refuses an expired link', function () use ($sellerLinkFor): void {
    config(['magic_links.expiry_minutes' => 15]);
    $url = $sellerLinkFor('artist@example.com');

    $this->travel(16)->minutes();
    $response = $this->get($url);

    $response->assertRedirect(route('auth.seller.login'));
    $response->assertSessionHas('error', 'That sign-in link has expired. Ask for a new one.');
    $this->assertGuest('seller');
    expect(Seller::count())->toBe(0);
});

it('only lets a link work once', function () use ($sellerLinkFor): void {
    $url = $sellerLinkFor('artist@example.com');
    $this->get($url);
    $this->post('/seller/logout');

    $response = $this->get($url);

    $response->assertRedirect(route('auth.seller.login'));
    $response->assertSessionHas('error', 'That sign-in link has already been used. Ask for a new one.');
    $this->assertGuest('seller');
});

it('refuses an unknown token', function (): void {
    $response = $this->get('/auth/magic/'.str_repeat('a', 80));

    $response->assertRedirect(route('auth.customer.login'));
    $response->assertSessionHas('error', 'That sign-in link is not valid. Ask for a new one.');
});

it('signs nobody in when the link is consumed between the read and the write', function () use ($sellerLinkFor): void {
    $url = $sellerLinkFor('artist@example.com');

    // The other verification lands after this request read the row and before
    // it wrote to it — the window the row count closes. Writing through the
    // query builder leaves the instance the request is holding stale, which
    // is what the losing side of a real race is holding too.
    MagicLink::retrieved(fn (MagicLink $link) => MagicLink::query()
        ->whereKey($link->id)
        ->update(['consumed_at' => now()]));

    $response = $this->get($url);

    $response->assertRedirect(route('auth.seller.login'));
    $response->assertSessionHas('error', 'That sign-in link has already been used. Ask for a new one.');
    $this->assertGuest('seller');
    expect(Seller::count())->toBe(0);
});

it('marks the link consumed on verification', function () use ($sellerLinkFor): void {
    $this->get($sellerLinkFor('artist@example.com'));

    expect(MagicLink::sole()->consumed_at)->not->toBeNull();
});

it('lets an anonymous customer claim their own row', function () use ($customerLinkFor): void {
    $anonymous = Customer::factory()->anonymous()->create();

    $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)
        ->get($customerLinkFor('shopper@example.com'));

    $response->assertRedirect(route('shop.account'));
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $anonymous->id);
    $this->assertAuthenticatedAs($anonymous->refresh(), 'customer');
    expect(Customer::count())->toBe(1)
        ->and($anonymous->email)->toBe('shopper@example.com');
    $this->assertDatabaseCount('customer_merges', 0);
});

it('merges an anonymous customer into the account that owns the address', function () use ($customerLinkFor): void {
    $verified = Customer::factory()->create(['email' => 'shopper@example.com']);
    $anonymous = Customer::factory()->anonymous()->create();

    $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)
        ->get($customerLinkFor('shopper@example.com'));

    $response->assertRedirect(route('shop.account'));
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $verified->id);
    $this->assertAuthenticatedAs($verified->refresh(), 'customer');
    $this->assertDatabaseHas('customer_merges', [
        'anonymous_customer_id' => $anonymous->id,
        'customer_id' => $verified->id,
    ]);
});

it('gives a customer with no cookie a fresh verified account', function () use ($customerLinkFor): void {
    $response = $this->get($customerLinkFor('shopper@example.com'));

    $customer = Customer::sole();
    $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
    $this->assertAuthenticatedAs($customer, 'customer');
    expect($customer->email_verified_at)->not->toBeNull();
});

it('marks a guest order address verified once verified', function () use ($customerLinkFor): void {
    $customer = Customer::factory()->create([
        'email' => 'shopper@example.com',
        'email_verified_at' => null,
    ]);

    $this->get($customerLinkFor('shopper@example.com'));

    expect($customer->refresh()->email_verified_at)->not->toBeNull();
});

it('honours a local destination on the link', function () use ($customerLinkFor): void {
    $response = $this->get($customerLinkFor('shopper@example.com', '/checkout'));

    $response->assertRedirect('/checkout');
});

it('ignores a destination on another host', function () use ($flashedLink): void {
    $this->post('/login', ['email' => 'shopper@example.com']);
    MagicLink::sole()->forceFill(['redirect_to' => 'http://evil.example/steal'])->save();

    $response = $this->get($flashedLink());

    $response->assertRedirect(route('shop.account'));
});

it('signs an existing admin in and lands them on the admin dashboard', function () use ($adminLinkFor): void {
    Admin::factory()->create(['email' => 'ops@example.com']);

    $response = $this->get($adminLinkFor('ops@example.com'));

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated('admin');
});

it('answers 404 and creates no admin when the row a link was issued for is gone', function () use ($adminLinkFor): void {
    $admin = Admin::factory()->create(['email' => 'ops@example.com']);
    $link = $adminLinkFor('ops@example.com');
    $admin->delete();

    $response = $this->get($link);

    $response->assertNotFound();
    expect(Admin::count())->toBe(0);
    $this->assertGuest('admin');
});

it('keeps a magic link from landing outside the portal it was issued for', function (
    string $email,
    string $loginRoute,
    bool $accountMustAlreadyExist,
    string $target,
    string $expectedLandingRoute,
) use ($flashedLink): void {
    if ($accountMustAlreadyExist) {
        Admin::factory()->create(['email' => $email]);
    }

    $this->post($loginRoute, ['email' => $email]);
    MagicLink::sole()->forceFill(['redirect_to' => $target])->save();

    $response = $this->get($flashedLink());

    $response->assertRedirect(route($expectedLandingRoute));
})->with([
    'a customer link out of the seller portal' => ['shopper@example.com', '/login', false, '/seller/dashboard', 'shop.account'],
    'a customer link out of the admin site' => ['shopper@example.com', '/login', false, '/admin', 'shop.account'],
    'a seller link out of the admin site' => ['artist@example.com', '/seller/login', false, '/admin', 'seller.dashboard'],
    'an admin link out of the seller portal' => ['ops@example.com', '/admin/login', true, '/seller/dashboard', 'admin.dashboard'],
]);

it('trips the verification limit by ip, answering 429 before the link is even read', function () use ($customerLinkFor): void {
    Config::set('rate_limits.magic_link_consume', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_CONSUME'));
    $link = $customerLinkFor('shopper@example.com');
    $this->get($link);

    $response = $this->get('/auth/magic/'.str_repeat('a1b2', 20));

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
});

it('resets the verification limit once its window passes', function () use ($customerLinkFor): void {
    Config::set('rate_limits.magic_link_consume', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_CONSUME'));
    $this->get($customerLinkFor('first@example.com'));

    $this->travel(16)->minutes();
    $link = $customerLinkFor('second@example.com');
    $response = $this->get($link);

    $response->assertRedirect(route('shop.account'));
});

it('logs the verification trip as rate_limit.exceed at warn, keyed by ip', function () use ($customerLinkFor): void {
    Config::set('rate_limits.magic_link_consume', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_CONSUME'));
    $this->get($customerLinkFor('shopper@example.com'));

    $log = CapturedStory::capture();
    $this->get('/auth/magic/'.str_repeat('a1b2', 20));

    $line = $log->line('rate_limit.exceed', 'refused');

    /** @var array<string, mixed> $data */
    $data = $line['data'];

    expect($line['level'])->toBe('warn')
        ->and($data['limit'])->toBe('magic_link_consume')
        ->and($data['key'])->toStartWith('ip:')
        ->and($log->linesFor('magic_link.consume'))->toBeEmpty();
});
