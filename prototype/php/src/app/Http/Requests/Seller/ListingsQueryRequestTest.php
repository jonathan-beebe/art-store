<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('defaults to the list view with nothing in the query string', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings');

    $response->assertOk();
});

it('accepts every documented view', function (string $view): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings?view={$view}");

    $response->assertOk();
})->with(['list', 'table', 'grid']);

it('answers 400 on a view outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings?view=bogus');

    $response->assertStatus(400);
});

it('accepts every documented sort column', function (string $column): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings?view=table&sort={$column}");

    $response->assertOk();
})->with(['title', 'status', 'price', 'stock', 'views', 'favorites', 'cart_adds', 'sold', 'revenue', 'conversion', 'updated']);

it('answers 400 on a sort column outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings?view=table&sort=bogus');

    $response->assertStatus(400);
});

it('accepts both sort directions', function (string $dir): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings?view=table&dir={$dir}");

    $response->assertOk();
})->with(['asc', 'desc']);

it('answers 400 on a direction outside asc or desc', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings?view=table&dir=sideways');

    $response->assertStatus(400);
});

it('accepts every documented range', function (string $range): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings?view=table&range={$range}");

    $response->assertOk();
})->with(['7', '30', '90']);

it('answers 400 on a range outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings?view=table&range=14');

    $response->assertStatus(400);
});

it('reads emptied query values as absent rather than as values to reject', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings?view=&sort=&dir=&range=');

    $response->assertOk();
});

it('accepts every documented from value on the detail route', function (string $from): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}?from={$from}");

    $response->assertOk();
})->with(['table', 'grid']);

it('answers 400 on a from value outside the documented set', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}?from=list");

    $response->assertStatus(400);
});

it('answers 400 on a detail route range outside the documented set', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}?range=14");

    $response->assertStatus(400);
});

it('leaves the detail route to its default with nothing in the query string', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertOk();
});
