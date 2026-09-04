<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('defaults to the all domain with nothing in the query string', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages');

    $response->assertOk();
});

it('accepts every documented domain value', function (string $domain): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/messages?domain={$domain}");

    $response->assertOk();
})->with(['all', 'sellers', 'customers']);

it('answers 400 on a domain value outside the documented set', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?domain=bogus');

    $response->assertStatus(400);
});

it('reads an emptied domain as absent rather than as a value to reject', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?domain=');

    $response->assertOk();
});
