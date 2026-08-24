<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\FinalizeOrder;
use App\Models\Customer;

it('lists every order with its customer', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');

    $response->assertOk();
    $response->assertSee($order->id);
    $response->assertSee('Ada Painter');
});

it('narrows the list to one status', function (): void {
    $awaiting = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $paid = app(FinalizeOrder::class)(
        $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller())),
        '4242424242424242',
        $this->moment('2026-08-20 10:00:00'),
    );

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders?status=paid');

    $response->assertOk();
    $response->assertSee($paid->id);
    $response->assertDontSee($awaiting->id);
});

it('narrows the list to one customer', function (): void {
    $ada = Customer::factory()->create(['name' => 'Ada Painter']);
    $mine = $this->orderFor($ada, $this->listing($this->seller()));
    $theirs = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders?customer={$ada->id}");

    $response->assertOk();
    $response->assertSee($mine->id);
    $response->assertDontSee($theirs->id);
});

it('reads an empty filter as every order, the way the console submits it', function (string $query): void {
    $first = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $second = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders?{$query}");

    $response->assertOk();
    $response->assertSee($first->id);
    $response->assertSee($second->id);
})->with([
    'no filters at all' => '',
    'both filters empty' => 'status=&customer=',
    'a status that names nothing' => 'status=nonsense',
]);

it('says so when no order matches the filters', function (): void {
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders?status=cancelled');

    $response->assertOk();
    $response->assertSee('No orders.');
});

it('shows one order with its items, payments and fulfillments', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
    $response->assertSee('Approved');
    $response->assertSee('4242');

    foreach ($order->fulfillments as $fulfillment) {
        $response->assertSee($fulfillment->id);
    }
});

it('says so on an order nobody has paid for yet, whose fulfillment is already waiting', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('No payment attempts yet.');
    $response->assertSee('Awaiting shipment');
});

it('sends a guest to the admin login page', function (): void {
    $this->get('/admin/orders')->assertRedirect(route('auth.admin.login'));
});

it('answers not found for a value that is not an order id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'sel_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'an order that does not exist' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);
