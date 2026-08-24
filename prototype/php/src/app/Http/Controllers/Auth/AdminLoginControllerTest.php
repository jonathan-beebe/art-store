<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ActorType;
use App\Models\Admin;
use App\Models\MagicLink;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;

it('renders an email form', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('name="email"', escape: false);
    $response->assertSee('action="'.route('auth.admin.send').'"', escape: false);
});

it('issues an admin link for an address with an admin row', function (): void {
    Admin::factory()->create(['email' => 'ops@example.com']);

    $this->post('/admin/login', ['email' => 'ops@example.com']);

    $link = MagicLink::sole();
    expect($link->email)->toBe('ops@example.com')
        ->and($link->actor_type)->toBe(ActorType::Admin);
});

it('issues no link and creates no admin for an address with no admin row', function (): void {
    $this->post('/admin/login', ['email' => 'nobody@example.com']);

    expect(MagicLink::count())->toBe(0)
        ->and(Admin::count())->toBe(0);
});

it('tells the visitor to check their email either way', function (): void {
    $known = $this->followingRedirects()->post('/admin/login', ['email' => 'unknown@example.com']);
    $known->assertSee('Check your email');
    $known->assertSee('unknown@example.com');

    Admin::factory()->create(['email' => 'ops@example.com']);
    $admitted = $this->followingRedirects()->post('/admin/login', ['email' => 'ops@example.com']);
    $admitted->assertSee('Check your email');
    $admitted->assertSee('ops@example.com');
});

it('answers the same page byte for byte whether or not the address has an admin', function (): void {
    // The prototype's own delivery flashes the link to the session for the
    // debug alert, which only an admitted address gets; mail is the delivery
    // a deployment runs, and the one this page is probed under.
    config(['magic_links.delivery' => 'mail']);

    $unknown = $this->followingRedirects()->post('/admin/login', ['email' => 'ops@example.com']);
    Admin::factory()->create(['email' => 'ops@example.com']);
    $admitted = $this->followingRedirects()->post('/admin/login', ['email' => 'ops@example.com']);

    expect($admitted->getStatusCode())->toBe($unknown->getStatusCode())
        ->and($admitted->getContent())->toBe($unknown->getContent());
});

it('flashes the link for the debug alert only when the address admits an admin', function (): void {
    Admin::factory()->create(['email' => 'ops@example.com']);

    $this->post('/admin/login', ['email' => 'ops@example.com']);

    expect(Arr::string(Session::all(), 'debug_magic_link'))->toStartWith(url('/auth/magic').'/');
});

it('flashes a debug notice for an address with no admin row, naming no seeded admin address', function (): void {
    $this->post('/admin/login', ['email' => 'nobody@example.com']);

    $notice = Arr::string(Session::all(), 'debug_notice');
    expect($notice)->toContain('nobody@example.com');

    foreach (AdminSeeder::ADMINS as $admin) {
        expect($notice)->not->toContain($admin['email']);
    }
});

it('shows the debug notice on the redirected page for an address with no admin row', function (): void {
    $response = $this->followingRedirects()->post('/admin/login', ['email' => 'nobody@example.com']);

    $response->assertSee('nobody@example.com');

    foreach (AdminSeeder::ADMINS as $admin) {
        $response->assertDontSee($admin['email']);
    }
});

it('flashes no debug notice for an address with an admin row', function (): void {
    Admin::factory()->create(['email' => 'ops@example.com']);

    $this->post('/admin/login', ['email' => 'ops@example.com']);

    expect(Session::all())->not->toHaveKey('debug_notice');
});

it('flashes no debug notice under mail delivery', function (): void {
    config(['magic_links.delivery' => 'mail']);

    $this->post('/admin/login', ['email' => 'nobody@example.com']);

    expect(Session::all())->not->toHaveKey('debug_notice');
});

it('sends a signed in admin to the dashboard', function (): void {
    $response = $this->actingAs(Admin::factory()->create(), 'admin')->get('/admin/login');

    $response->assertRedirect(route('admin.dashboard'));
});
