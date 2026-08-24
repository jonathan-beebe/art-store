<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

it('opens an event stream for the signed-in admin', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/events');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
});

it('sends a guest to admin sign-in', function (): void {
    $response = $this->get('/admin/events');

    $response->assertRedirect(route('auth.admin.login'));
});
