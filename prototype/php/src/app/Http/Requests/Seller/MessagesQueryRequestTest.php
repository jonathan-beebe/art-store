<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('defaults to all threads, open only, with nothing in the query string', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages');

    $response->assertOk();
});

it('accepts every documented filter and status value', function (string $filter, string $status): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/messages?filter={$filter}&status={$status}");

    $response->assertOk();
})->with([
    ['all', 'open'],
    ['unread', 'open'],
    ['questions', 'open'],
    ['orders', 'open'],
    ['support', 'open'],
    ['all', 'resolved'],
    ['all', 'all'],
]);

it('answers 400 on a filter value outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages?filter=bogus');

    $response->assertStatus(400);
});

it('answers 400 on a status value outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages?status=bogus');

    $response->assertStatus(400);
});

it('reads an emptied query value as absent rather than as a value to reject', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages?filter=&status=');

    $response->assertOk();
});
