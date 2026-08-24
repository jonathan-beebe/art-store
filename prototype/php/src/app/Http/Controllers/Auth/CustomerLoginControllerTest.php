<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ActorType;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Customer;
use App\Models\MagicLink;
use Illuminate\Support\Facades\Config;
use Tests\CapturedStory;

it('renders an email form', function (): void {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertSee('name="email"', escape: false);
    $response->assertSee('action="'.route('auth.customer.send').'"', escape: false);
});

it('issues a customer link for the submitted address', function (): void {
    $this->post('/login', ['email' => 'shopper@example.com']);

    $link = MagicLink::sole();
    expect($link->email)->toBe('shopper@example.com')
        ->and($link->actor_type)->toBe(ActorType::Customer);
});

it('tells the visitor to check their email', function (): void {
    $response = $this->followingRedirects()->post('/login', ['email' => 'shopper@example.com']);

    $response->assertSee('Check your email');
    $response->assertSee('shopper@example.com');
});

it('sends a signed in customer to their account', function (): void {
    $response = $this->actingAs(Customer::factory()->create(), 'customer')->get('/login');

    $response->assertRedirect(route('shop.account'));
});

it('trips the magic-link limit for a repeated address, re-rendering the form with no link issued', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->post('/login', ['email' => 'shopper@example.com']);

    $response = $this->post('/login', ['email' => 'shopper@example.com']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    expect(MagicLink::count())->toBe(1);
});

it('trips the magic-link limit by ip even across different addresses', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->post('/login', ['email' => 'first@example.com']);

    $response = $this->post('/login', ['email' => 'second@example.com']);

    $response->assertStatus(429);
    expect(MagicLink::count())->toBe(1);
});

it('resets the magic-link limit once its window passes', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->post('/login', ['email' => 'shopper@example.com']);

    $this->travel(16)->minutes();
    $response = $this->post('/login', ['email' => 'shopper@example.com']);

    $response->assertRedirect(route('auth.customer.login'));
    expect(MagicLink::count())->toBe(2);
});

it('logs the trip as rate_limit.exceed at warn with the email hashed rather than in the clear', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->post('/login', ['email' => 'shopper@example.com']);

    $log = CapturedStory::capture();
    $this->post('/login', ['email' => 'shopper@example.com']);

    $line = $log->line('rate_limit.exceed', 'refused');

    /** @var array<string, mixed> $data */
    $data = $line['data'];

    expect($line['level'])->toBe('warn')
        ->and($data['limit'])->toBe('magic_link_request')
        ->and($data['key'])->toStartWith('email:')
        ->and($data['key'])->not->toContain('shopper@example.com')
        ->and($log->raw())->not->toContain('shopper@example.com');
});

it('does not rate limit magic-link requests when the limit is off', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('off', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));

    for ($i = 0; $i < 10; $i++) {
        $this->post('/login', ['email' => 'shopper@example.com']);
    }

    expect(MagicLink::count())->toBe(10);
});
