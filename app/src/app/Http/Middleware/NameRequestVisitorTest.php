<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identifiers\PrefixedId;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\Seller;
use App\Shop\CustomerIdentity;
use Tests\CapturedStory;

it('mints the session cookie on the first response a browser gets', function (): void {
    $log = CapturedStory::capture();

    $response = $this->get('/');

    $sessionId = $log->line('http.request', 'did')['session_id'];

    expect(PrefixedId::parse('ses', is_string($sessionId) ? $sessionId : ''))->not->toBeNull();

    $response->assertCookie(NameRequestVisitor::SESSION_COOKIE, $sessionId);
    expect($response->getCookie(NameRequestVisitor::SESSION_COOKIE)?->getExpiresTime())
        ->toBeGreaterThan(now()->addDays(360)->getTimestamp());
});

it('keeps the session the browser already holds', function (): void {
    $log = CapturedStory::capture();

    $held = 'ses_01J00000000000000000000ABC';

    $this->withCookie(NameRequestVisitor::SESSION_COOKIE, $held)->get('/');

    expect($log->line('http.request', 'did')['session_id'])->toBe($held);
});

it('mints a fresh session when the cookie holds something that is not one', function (): void {
    $log = CapturedStory::capture();

    $this->withCookie(NameRequestVisitor::SESSION_COOKIE, 'cus_01J00000000000000000000ABC')->get('/');

    expect($log->line('http.request', 'did')['session_id'])->not->toBe('cus_01J00000000000000000000ABC');
});

it('carries one session through signing in and signing out', function (): void {
    $held = 'ses_01J00000000000000000000ABC';
    $customer = Customer::factory()->create();

    $log = CapturedStory::capture();

    $this->withCookie(NameRequestVisitor::SESSION_COOKIE, $held)
        ->actingAs($customer, 'customer')
        ->get('/account');

    $this->withCookie(NameRequestVisitor::SESSION_COOKIE, $held)->post('/logout');

    expect(array_values(array_unique($log->values('session_id', 'http.request'))))->toBe([$held]);
});

it('names the actor behind the request', function (): void {
    $seller = Seller::factory()->create();

    $log = CapturedStory::capture();

    $this->actingAs($seller, 'seller')->get('/seller');

    expect($log->line('http.request', 'did'))
        ->toHaveKey('actor_type', 'seller')
        ->toHaveKey('actor_id', $seller->id);
});

it('names an admin behind the request', function (): void {
    $admin = Admin::factory()->create();

    $log = CapturedStory::capture();

    $this->actingAs($admin, 'admin')->get('/admin');

    expect($log->line('http.request', 'did'))->toHaveKey('actor_type', 'admin');
});

it('names an anonymous visitor by the identity cookie they already hold', function (): void {
    $visitor = Customer::factory()->anonymous()->create();

    $log = CapturedStory::capture();

    $this->withCookie(CustomerIdentity::COOKIE, (string) $visitor->id)->get('/');

    expect($log->line('http.request', 'did'))
        ->toHaveKey('actor_type', 'customer')
        ->toHaveKey('actor_id', $visitor->id);
});

it('names no actor for a first-time browser on a read-only page', function (): void {
    $log = CapturedStory::capture();

    $this->get('/');

    expect($log->line('http.request', 'will'))->not->toHaveKey('actor_id')
        ->and($log->line('http.request', 'did'))->not->toHaveKey('actor_type');
});

it('names the visitor a first-time browser is given, from the line after the event that creates it', function (): void {
    Listing::factory()->create(['slug' => 'harbour-at-dawn']);
    $log = CapturedStory::capture();

    $this->get('/art/harbour-at-dawn');

    expect($log->line('http.request', 'will'))->not->toHaveKey('actor_id')
        ->and($log->line('http.request', 'did'))->toHaveKey('actor_type', 'customer');
});
