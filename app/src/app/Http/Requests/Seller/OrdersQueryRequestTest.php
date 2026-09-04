<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('opens the index on the default lane with nothing in the query string', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders');

    $response->assertOk();
    $response->assertSee('aria-current="page"', escape: false);
});

it('accepts every documented lane', function (string $lane): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/orders?lane={$lane}");

    $response->assertOk();
})->with(['ship', 'progress', 'done', 'all']);

it('answers 400 on a lane outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders?lane=bogus');

    $response->assertStatus(400);
});

it('reads an emptied lane as absent', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders?lane=');

    $response->assertOk();
});

it('accepts every documented lane on the detail route', function (string $lane): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?lane={$lane}");

    $response->assertOk();
})->with(['ship', 'progress', 'done', 'all']);

it('answers 400 on a lane the detail route does not know', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?lane=sideways");

    $response->assertStatus(400);
});

it('accepts every documented feed kind', function (string $kind): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?kind={$kind}");

    $response->assertOk();
})->with(['browse', 'order', 'shipping', 'messages']);

it('answers 400 on a feed kind outside the documented set', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?kind=weather");

    $response->assertStatus(400);
});

it('reads an emptied kind as the whole feed', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}?kind=");

    $response->assertOk();
    $response->assertSee('placed order');
});

it('answers 400 before it answers not found on another sellers parcel', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Rye Press'));

    $response = $this->actingAs($this->seller('Blue Kiln Studio'), 'seller')->get("/seller/orders/{$fulfillment->id}?lane=bogus");

    $response->assertStatus(400);
});
