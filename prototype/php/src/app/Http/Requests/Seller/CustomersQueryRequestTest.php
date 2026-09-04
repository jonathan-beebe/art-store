<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Customer;

it('opens on the defaults with nothing in the query string', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers');

    $response->assertOk();
});

it('accepts every documented segment', function (string $segment): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/customers?segment={$segment}");

    $response->assertOk();
})->with(['all', 'repeat', 'new']);

it('answers 400 on a segment outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers?segment=bogus');

    $response->assertStatus(400);
});

it('accepts every documented sort column', function (string $column): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/customers?sort={$column}");

    $response->assertOk();
})->with(['name', 'orders', 'spent', 'favorites', 'last_order', 'conversations', 'since']);

it('answers 400 on a sort column outside the documented set', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers?sort=bogus');

    $response->assertStatus(400);
});

it('accepts both sort directions', function (string $direction): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/customers?dir={$direction}");

    $response->assertOk();
})->with(['asc', 'desc']);

it('answers 400 on a direction outside asc or desc', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers?dir=sideways');

    $response->assertStatus(400);
});

it('ignores a range on the index route, whatever value it carries', function (string $range): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/customers?range={$range}");

    $response->assertOk();
})->with(['7', '30', '90', '14', 'bogus']);

it('reads emptied query values as absent', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers?range=&segment=&sort=&dir=');

    $response->assertOk();
});

it('IMPRV-038 opens page one with nothing to show', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers?page=1');

    $response->assertOk();
});

it('IMPRV-038 answers 400 on page zero', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers?page=0');

    $response->assertStatus(400);
});

it('IMPRV-038 answers 400 on a non-integer page', function (string $page): void {
    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/customers?page={$page}");

    $response->assertStatus(400);
})->with(['abc', '1.5', '-1']);

it('IMPRV-038 answers 400 on a page past the end', function (): void {
    $seller = $this->seller();
    $this->paidFulfillmentFor($seller, Customer::factory()->create());

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?page=2');

    $response->assertStatus(400);
});

it('IMPRV-038 accepts the last page exactly', function (): void {
    $seller = $this->seller();
    foreach (range(1, 51) as $i) {
        $this->paidFulfillmentFor($seller, Customer::factory()->create());
    }

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?page=2');

    $response->assertOk();
});

it('accepts every documented feed kind on the customer page', function (string $kind): void {
    $seller = $this->seller();
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood']);
    $this->paidFulfillmentFor($seller, $customer);

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}?kind={$kind}");

    $response->assertOk();
})->with(['browse', 'order', 'shipping', 'messages']);

it('answers 400 on a feed kind outside the documented set', function (): void {
    $seller = $this->seller();
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood']);
    $this->paidFulfillmentFor($seller, $customer);

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}?kind=bogus");

    $response->assertStatus(400);
});
