<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Cart\AddToCart;
use App\Actions\Customers\BlockCustomer;
use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Models\Favorite;
use App\Support\ListPaneWindow;

it('lists every customer with their standing', function (): void {
    $blocked = Customer::factory()->create(['name' => 'Ada Painter']);
    app(BlockCustomer::class)($blocked, 'Chargeback fraud.');
    $ok = Customer::factory()->create(['name' => 'Priya Shopper']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/customers');

    $response->assertOk();
    $response->assertSeeInOrder(['Ada Painter', 'Blocked']);
    $response->assertSeeInOrder(['Priya Shopper', 'Verified']);
});

it('shows one customer with their orders and current standing', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$customer->id}");

    $response->assertOk();
    $response->assertSee('Ada Painter');
    $response->assertSee($order->id);
    $response->assertSee('Not blocked.');
});

it('shows the block reason and a lift form for a blocked customer', function (): void {
    $customer = Customer::factory()->create();
    app(BlockCustomer::class)($customer, 'Chargeback fraud.');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$customer->id}");

    $response->assertSee('Chargeback fraud.');
    $response->assertSee('Lift block');
});

it('offers a form to message the customer', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$customer->id}");

    $response->assertSee('Message customer');
    $response->assertSee('action="'.route('admin.customers.messages', $customer).'"', escape: false);
});

it('sends a guest to the admin login page', function (): void {
    $response = $this->get('/admin/customers');

    $response->assertRedirect(route('auth.admin.login'));
});

it('answers not found for a value that is not a customer id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'sel_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a customer that does not exist' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

it('counts what each customer holds and names an anonymous visitor', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);
    $listing = $this->listing($this->seller());
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);
    app(AddToCart::class)($this->cartFor($customer), $listing, 2, $this->moment('2026-08-20 08:00:00'));
    $this->orderFor($customer, $this->listing($this->seller()));
    $anonymous = $this->anonymousCustomer();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/customers');

    $response->assertOk();
    $response->assertSee($anonymous->id);
    $response->assertSee('Anonymous');
    $response->assertSeeInOrder(['Ada Painter', '1', '1', '1']);
});

it('narrows the list to one standing', function (string $query, string $seen, string $hidden): void {
    $verified = Customer::factory()->create(['name' => 'Vera Verified']);
    $anonymous = Customer::factory()->anonymous()->create();
    $blocked = Customer::factory()->create(['name' => 'Bob Blocked']);
    app(BlockCustomer::class)($blocked, 'Chargeback fraud.');

    $names = ['verified' => 'Vera Verified', 'anonymous' => $anonymous->id, 'blocked' => 'Bob Blocked'];

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers?{$query}");

    $response->assertOk();
    $response->assertSee($names[$seen]);
    $response->assertDontSee($names[$hidden]);
})->with([
    'verified only' => ['standing=verified', 'verified', 'anonymous'],
    'anonymous only' => ['standing=anonymous', 'anonymous', 'verified'],
    'blocked only' => ['standing=blocked', 'blocked', 'anonymous'],
]);

it('reads an empty standing as every customer, the way the console submits it', function (string $query): void {
    $verified = Customer::factory()->create(['name' => 'Vera Verified']);
    $anonymous = Customer::factory()->anonymous()->create();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers?{$query}");

    $response->assertOk();
    $response->assertSee('Vera Verified');
    $response->assertSee($anonymous->id);
})->with([
    'no filter at all' => '',
    'an empty filter' => 'standing=',
    'the all filter' => 'standing=all',
    'a value that names no standing' => 'standing=nonsense',
]);

it('shows the favorites, cart, block history and merge history behind a customer', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);
    $listing = $this->listing($this->seller(), ['title' => 'Nine Herons']);
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);
    app(AddToCart::class)($this->cartFor($customer), $this->listing($this->seller(), ['title' => 'Rye Harvest']), 1, $this->moment('2026-08-20 08:00:00'));
    app(BlockCustomer::class)($customer, 'Chargeback fraud.');
    $anonymous = $this->anonymousCustomer();
    CustomerMerge::create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $customer->id]);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$customer->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Rye Harvest');
    $response->assertSee('Block history');
    $response->assertSee('Absorbed');
    $response->assertSee($anonymous->id);
});

it('says so on a customer who has done nothing at all', function (): void {
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$customer->id}");

    $response->assertOk();
    $response->assertSee('No orders.');
    $response->assertSee('No favorites.');
    $response->assertSee('Cart is empty.');
    $response->assertSee('Never blocked.');
    $response->assertSee('No merges.');
});

it('shows the list panes empty-detail prompt on the index route', function (): void {
    Customer::factory()->create(['name' => 'Ada Painter']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/customers');

    $response->assertOk();
    $response->assertSee('Choose a customer to see their account.');
});

it('renders the list pane beside the detail pane, with a sibling customer still on the list', function (): void {
    Customer::factory()->create(['name' => 'Priya Shopper']);
    $viewed = Customer::factory()->create(['name' => 'Ada Painter']);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Ada Painter');
    $response->assertSee('Priya Shopper');
});

it('shows the merge that folded an anonymous visitor into someone else', function (): void {
    $anonymous = $this->anonymousCustomer();
    $customer = Customer::factory()->create(['name' => 'Ada Painter']);
    CustomerMerge::create(['anonymous_customer_id' => $anonymous->id, 'customer_id' => $customer->id]);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$anonymous->id}");

    $response->assertOk();
    $response->assertSee('Folded into');
    $response->assertSee($customer->id);
});

it('caps the list pane at the window size, however many customers exist', function (): void {
    Customer::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/customers');

    $response->assertOk();
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE);
});

it('keeps the viewed customer on the list pane even when they sort outside the window', function (): void {
    $viewed = Customer::factory()->create(['name' => 'Ada Painter', 'created_at' => now()->subDay()]);
    Customer::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/customers/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Ada Painter');
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE + 1);
});

it('says how many customers the list pane is not showing, linked to the full list', function (): void {
    Customer::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/customers');

    $response->assertOk();
    $response->assertSee('Showing 50 of', false);
    $response->assertSee('href="'.route('admin.customers.index').'"', escape: false);
});

it('says nothing about a window that already holds every customer', function (): void {
    Customer::factory()->create(['name' => 'Ada Painter']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/customers');

    $response->assertOk();
    $response->assertDontSee('Showing');
});
