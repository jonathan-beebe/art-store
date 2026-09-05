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
    expect(substr_count((string) $response->getContent(), 'Too many requests'))->toBe(1);
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

it('renders no guest header linking to the dashboard or back to sign in', function (): void {
    $response = $this->get('/seller/login');

    $response->assertDontSee('href="'.route('seller.dashboard').'"', escape: false);
    $response->assertDontSee('href="'.route('auth.seller.login').'"', escape: false);
});

it('carries exactly one heading, email field, and submit button', function (): void {
    $html = (string) $this->get('/seller/login')->getContent();

    expect(substr_count($html, '<h1'))->toBe(1);
    preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $heading);
    expect(trim($heading[1] ?? ''))->toBe('Sign in');

    expect(substr_count($html, 'name="email"'))->toBe(1);

    expect(substr_count($html, '<button'))->toBe(1);
    preg_match('#<button[^>]*>(.*?)</button>#s', $html, $button);
    expect(trim($button[1] ?? ''))->toBe('Email me a sign-in link');
});

it('keeps the footer line about needing no password', function (): void {
    $response = $this->get('/seller/login');

    $response->assertSee('No password. Selling for the first time? The link creates your shop.');
});

it('shows the confirmation with no guest header after a successful request', function (): void {
    $response = $this->followingRedirects()->post('/seller/login', ['email' => 'artist@example.com']);

    $response->assertSee('Check your email');
    $response->assertSee('artist@example.com');
    $response->assertDontSee('href="'.route('seller.dashboard').'"', escape: false);
    $response->assertDontSee('href="'.route('auth.seller.login').'"', escape: false);
});

it('shows the rate-limit error with no guest header', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->post('/seller/login', ['email' => 'artist@example.com']);

    $response = $this->post('/seller/login', ['email' => 'artist@example.com']);

    $response->assertSee('Too many requests', escape: false);
    $response->assertDontSee('href="'.route('seller.dashboard').'"', escape: false);
    $response->assertDontSee('href="'.route('auth.seller.login').'"', escape: false);
});

it('renders the invalid-email validation message exactly once', function (): void {
    $response = $this->from('/seller/login')->followingRedirects()->post('/seller/login', ['email' => 'not-an-email']);

    $html = (string) $response->getContent();
    expect(substr_count($html, 'The email field must be a valid email address.'))->toBe(1);
});
