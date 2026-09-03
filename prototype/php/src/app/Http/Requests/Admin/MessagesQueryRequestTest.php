<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('defaults to every type, open only, with nothing in the query string', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages');

    $response->assertOk();
});

it('accepts a domain, type, and status combined in one query string', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get(
        '/admin/messages?domain=sellers&type[]=support&status[]=open&status[]=needs-reply',
    );

    $response->assertOk();
});

it('accepts every documented domain value', function (string $domain): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/messages?domain={$domain}");

    $response->assertOk();
})->with(['all', 'sellers', 'customers']);

it('accepts every documented type value', function (string $type): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/messages?type[]={$type}");

    $response->assertOk();
})->with(['questions', 'orders', 'support']);

it('accepts every documented status value', function (string $status): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/messages?status[]={$status}");

    $response->assertOk();
})->with(['open', 'resolved', 'needs-reply']);

it('answers 400 on a domain value outside the documented set', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?domain=bogus');

    $response->assertStatus(400);
});

it('answers 400 on a type member outside the documented set', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?type[]=bogus');

    $response->assertStatus(400);
});

it('answers 400 on a status member outside the documented set', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?status[]=bogus');

    $response->assertStatus(400);
});

it('answers 400 when type is not an array', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?type=questions');

    $response->assertStatus(400);
});

it('answers 400 when status is not an array', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?status=open');

    $response->assertStatus(400);
});

it('reads an emptied domain as absent rather than as a value to reject', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?domain=');

    $response->assertOk();
});
