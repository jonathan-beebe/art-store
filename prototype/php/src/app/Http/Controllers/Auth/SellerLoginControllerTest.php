<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\MagicLink;
use App\Models\Seller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

it('renders an email form', function (): void {
    $response = $this->get('/seller/login');

    $response->assertOk();
    $response->assertSee('name="email"', escape: false);
    $response->assertSee('action="'.route('auth.seller.send').'"', escape: false);
});

it('issues a seller link for the submitted address', function (): void {
    $this->post('/seller/login', ['email' => 'artist@example.com']);

    $link = MagicLink::sole();
    expect($link->email)->toBe('artist@example.com')
        ->and($link->actor_type)->toBe(ActorType::Seller);
});

it('tells the visitor to check their email', function (): void {
    $response = $this->followingRedirects()->post('/seller/login', ['email' => 'artist@example.com']);

    $response->assertSee('Check your email');
    $response->assertSee('artist@example.com');
});

it('flashes the link for the debug alert', function (): void {
    $this->post('/seller/login', ['email' => 'artist@example.com']);

    expect(Arr::string(Session::all(), 'debug_magic_link'))->toStartWith(url('/auth/magic').'/');
});

it('sends a signed in seller to the dashboard', function (): void {
    $response = $this->actingAs(Seller::factory()->create(), 'seller')->get('/seller/login');

    $response->assertRedirect(route('seller.dashboard'));
});

it('trips the magic-link limit for a repeated address, re-rendering the form with no link issued', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->post('/seller/login', ['email' => 'artist@example.com']);

    $response = $this->post('/seller/login', ['email' => 'artist@example.com']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    expect(MagicLink::count())->toBe(1);
});

it('resets the magic-link limit once its window passes', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->post('/seller/login', ['email' => 'artist@example.com']);

    $this->travel(16)->minutes();
    $response = $this->post('/seller/login', ['email' => 'artist@example.com']);

    $response->assertRedirect(route('auth.seller.login'));
    expect(MagicLink::count())->toBe(2);
});
