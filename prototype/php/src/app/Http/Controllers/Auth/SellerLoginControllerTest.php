<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ActorType;
use App\Models\MagicLink;
use App\Models\Seller;

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

    expect(session('debug_magic_link'))->toStartWith(url('/auth/magic/'));
});

it('sends a signed in seller to the dashboard', function (): void {
    $response = $this->actingAs(Seller::factory()->create(), 'seller')->get('/seller/login');

    $response->assertRedirect(route('seller.dashboard'));
});
