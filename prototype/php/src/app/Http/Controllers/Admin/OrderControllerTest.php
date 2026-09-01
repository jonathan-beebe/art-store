<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Variant;
use App\Support\ListPaneWindow;

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

it('renders a configured lines configuration and itemized breakdown', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Engraved Signet Ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);
    $variant = Variant::whereHas('options', fn ($query) => $query->where('option_value_id', $roseGold->id))->sole();

    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    app(AddToCart::class)(
        $cart,
        $listing,
        1,
        $this->moment('2026-08-20 08:00:00'),
        listingHasVariants: true,
        variant: $variant,
        configuration: [['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $roseGold->id, 'optionValueLabel' => 'Rose Gold']],
    );
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('Metal:');
    $response->assertSee('Rose Gold');
    $response->assertSee('Base price');
    $response->assertSee('$128.00');
});

it('answers not found for a value that is not an order id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'sel_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'an order that does not exist' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

it('offers to cancel an order nothing has been charged for', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('Cancel this order');
});

it('stops offering to cancel a paid order', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertDontSee('Cancel this order');
    $response->assertSee('Refund this fulfillment');
});

it('keeps the Orders nav link current on an order detail page, not just the index', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $index = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');
    $show = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    foreach ([$index, $show] as $response) {
        $response->assertOk();
        $html = (string) $response->getContent();
        // Two: the lg+ rail and the below-lg drawer (DSGN-006) — they share
        // one nav-items partial so they can't drift.
        expect(preg_match_all('/<a\s+href="'.preg_quote(route('admin.orders.index'), '/').'"\s+aria-current="page"/', $html))->toBe(2);
    }
});

it('opens with a back link to the order list, for below sm', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    expect($response->getContent())->toMatch('/<a href="'.preg_quote(route('admin.orders.index'), '/').'"[^>]*sm:hidden"[^>]*>\s*<svg[\s\S]*?<span>Orders<\/span>/');
});

it('says so on an order with no refunds', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('No refunds.');
});

it('shows the list pane and its empty-detail prompt on the index route', function (): void {
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');

    $response->assertOk();
    $response->assertSee('Choose an order to see its details.');
});

it('renders the list pane beside the detail pane, with a sibling order still on the list', function (): void {
    $ada = Customer::factory()->create(['name' => 'Ada Painter']);
    $this->orderFor($ada, $this->listing($this->seller()));
    $viewed = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$viewed->id}");

    $response->assertOk();
    $response->assertSee($viewed->id);
    $response->assertSee('Ada Painter');
    // The open order's own cell in the list pane carries the mark, and no
    // other cell does.
    expect(substr_count((string) $response->getContent(), 'aria-current="true"'))->toBe(1);
});

it('caps the list pane at the window size, however many orders exist', function (): void {
    Order::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');

    $response->assertOk();
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE);
});

it('keeps the viewed order on the list pane even when it sorts outside the window', function (): void {
    $viewed = Order::factory()->create(['placed_at' => now()->subDay()]);
    Order::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$viewed->id}");

    $response->assertOk();
    $response->assertSee($viewed->id);
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE + 1);
});

it('says how many orders the list pane is not showing, linked to the full list', function (): void {
    Order::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');

    $response->assertOk();
    $response->assertSee('Showing 50 of', false);
    $response->assertSee('href="'.route('admin.orders.index').'"', escape: false);
});

it('says nothing about a window that already holds every order', function (): void {
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');

    $response->assertOk();
    $response->assertDontSee('Showing');
});

it('lists the refunds issued on an order', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $fulfillment = $order->fulfillments()->orderBy('id')->firstOrFail();
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('The kiln cracked the glaze.');
    $response->assertSee('Seller');
    $response->assertSee('Nothing left to refund on this fulfillment.');
});
