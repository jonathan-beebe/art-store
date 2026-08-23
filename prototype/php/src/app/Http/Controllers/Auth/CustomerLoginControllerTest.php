<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ActorType;
use App\Models\Customer;
use App\Models\MagicLink;

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
