<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('defaults to the desks work queue when no query string is given', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages');

    $response->assertOk();
});

it('accepts every documented filter and status value', function (string $filter, string $status): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/messages?filter={$filter}&status={$status}");

    $response->assertOk();
})->with([
    ['needs-reply', 'open'],
    ['all', 'all'],
    ['sellers', 'resolved'],
    ['customers', 'open'],
    ['orders', 'all'],
    ['questions', 'resolved'],
]);

it('answers 400 for an unrecognised filter', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?filter=bogus');

    $response->assertStatus(400);
});

it('answers 400 for an unrecognised status', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?status=bogus');

    $response->assertStatus(400);
});

it('reads an emptied filter or status as absent, the way a blank select submits', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?filter=&status=');

    $response->assertOk();
});
