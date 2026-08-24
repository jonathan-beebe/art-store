<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Customer;

it('lists every fulfillment with its order and seller', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller('Blue Kiln Studio'));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/fulfillments');

    $response->assertOk();
    $response->assertSee($fulfillment->id);
    $response->assertSee($fulfillment->order->id);
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Shipped');
});

it('narrows the list to one status', function (): void {
    $shipped = $this->shippedFulfillmentFor($this->seller('Blue Kiln Studio'));
    $delivered = $this->deliveredFulfillmentFor($this->seller('Rye Press'));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/fulfillments?status=delivered');

    $response->assertOk();
    $response->assertSee($delivered->id);
    $response->assertDontSee($shipped->id);
});

it('narrows the list to one seller', function (): void {
    $kiln = $this->seller('Blue Kiln Studio');
    $mine = $this->shippedFulfillmentFor($kiln);
    $theirs = $this->shippedFulfillmentFor($this->seller('Rye Press'));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/fulfillments?seller={$kiln->id}");

    $response->assertOk();
    $response->assertSee($mine->id);
    $response->assertDontSee($theirs->id);
});

it('reads an empty filter as every fulfillment, the way the console submits it', function (string $query): void {
    $shipped = $this->shippedFulfillmentFor($this->seller('Blue Kiln Studio'));
    $delivered = $this->deliveredFulfillmentFor($this->seller('Rye Press'));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/fulfillments?{$query}");

    $response->assertOk();
    $response->assertSee($shipped->id);
    $response->assertSee($delivered->id);
})->with([
    'no filters at all' => '',
    'both filters empty' => 'status=&seller=',
    'a status that names nothing' => 'status=nonsense',
]);

it('says so when no fulfillment matches the filters', function (): void {
    $this->shippedFulfillmentFor($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/fulfillments?status=delivered');

    $response->assertOk();
    $response->assertSee('No fulfillments.');
});

it('shows one fulfillment with its items, money and ledger', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);
    $fulfillment = $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), $customer, priceCents: 10000);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/fulfillments/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Ada Painter');
    $response->assertSee('Royal Mail');
    $response->assertSee('RM123');
    $response->assertSee('$90.00');
    $response->assertSee('held');
    $response->assertSee('released');
});

it('says so on a fulfillment with nothing in escrow yet', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $fulfillment = $order->fulfillments()->sole();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/fulfillments/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('Nothing in escrow for this fulfillment yet.');
});

it('sends a guest to the admin login page', function (): void {
    $this->get('/admin/fulfillments')->assertRedirect(route('auth.admin.login'));
});

it('answers not found for a value that is not a fulfillment id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/fulfillments/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a fulfillment that does not exist' => 'ful_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);
