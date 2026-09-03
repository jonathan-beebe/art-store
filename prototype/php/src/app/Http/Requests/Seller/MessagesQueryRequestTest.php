<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('defaults to the all domain with nothing in the query string', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages');

    $response->assertOk();
});

it('accepts every documented domain value', function (string $domain): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/messages?domain={$domain}");

    $response->assertOk();
})->with(['all', 'buyers', 'support']);

it('answers 400 on a domain value outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages?domain=bogus');

    $response->assertStatus(400);
});

it('reads an emptied domain as absent rather than as a value to reject', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages?domain=');

    $response->assertOk();
});
