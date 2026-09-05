<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('reads no shape or title with nothing in the query string', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings/create');

    $response->assertOk();
});

it('accepts every documented shape', function (string $shape): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->get('/seller/listings/create?'.http_build_query(['title' => 'X', 'shape' => $shape]));

    $response->assertOk();
})->with(['one', 'versions', 'extras']);

it('normalizes a shape value outside the documented set to null', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->get('/seller/listings/create?'.http_build_query(['title' => 'X', 'shape' => 'bogus']));

    $response->assertOk();
});

it('reads an emptied shape as absent', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->get('/seller/listings/create?'.http_build_query(['title' => 'X', 'shape' => '']));

    $response->assertOk();
});
