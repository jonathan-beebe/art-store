<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

it('renders the admin dashboard', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

    $response->assertOk();
    $response->assertSee('Dashboard');
});

it('links to the sellers and customers pages', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

    $response->assertSee('href="'.route('admin.sellers.index').'"', escape: false);
    $response->assertSee('href="'.route('admin.customers.index').'"', escape: false);
});

it('sends a guest to the admin login page', function (): void {
    $response = $this->get('/admin');

    $response->assertRedirect(route('auth.admin.login'));
});

it('sends a signed in seller to the seller login wall, not the dashboard', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/admin');

    $response->assertRedirect(route('auth.admin.login'));
});
