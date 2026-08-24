<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Fulfillment;
use App\Models\Seller;

$paidFulfillment = function (Seller $seller, string $title = 'Harbour at Dusk'): Fulfillment {
    $order = test()->orderFor(test()->verifiedCustomer(), test()->listing($seller, ['title' => $title]));
    app(FinalizeOrder::class)($order, '4242424242424242', test()->moment('2026-08-20 10:00:00'));

    return Fulfillment::where('seller_id', $seller->id)->sole();
};

it('groups the fulfillments by status', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders');

    $response->assertOk();
    $response->assertSee('Awaiting shipment (1)');
    $response->assertSee('Shipped (0)');
    $response->assertSee('Delivered (0)');
});

it('keeps another sellers fulfillments off the page', function () use ($paidFulfillment): void {
    $paidFulfillment($this->seller('Other Studio'), 'Not Mine');

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders');

    $response->assertDontSee('Not Mine');
});

it('shows the shipping address and the sellers items', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller, 'Harbour at Dusk');

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('Ada Lovelace');
    $response->assertSee('12 Analytical Way');
    $response->assertSee('Harbour at Dusk');
});

it('leaves another sellers items off the order', function (): void {
    $seller = $this->seller();
    $other = $this->seller('Other Studio');
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor(
        $customer,
        $this->listing($seller, ['title' => 'Mine']),
        $this->listing($other, ['title' => 'Theirs']),
    );
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Mine');
    $response->assertDontSee('Theirs');
});

it('offers the mark shipped form while a fulfillment awaits shipment', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('for="carrier"', escape: false);
    $response->assertSee('for="tracking_number"', escape: false);
});

it('shows the shipment details once shipped', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123', $this->moment('2026-08-21 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Royal Mail');
    $response->assertSee('RM123');
    $response->assertSee('Aug 21, 2026');
    $response->assertDontSee('for="carrier"', escape: false);
});

it('shows the delivered timestamp', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123', $this->moment('2026-08-21 10:00:00'));
    app(ConfirmDelivered::class)($fulfillment->refresh(), $this->moment('2026-08-23 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertSee('Aug 23, 2026');
});

it('hides another sellers fulfillment', function () use ($paidFulfillment): void {
    $fulfillment = $paidFulfillment($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertNotFound();
});

it('answers not found for a value that is not a fulfillment id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->seller(), 'seller')->get("/seller/orders/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a fulfillment that does not exist' => 'ful_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

it('offers the decline form on a parcel that has not shipped', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('Decline and refund');
});

it('withdraws the decline form once the parcel shipped', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertDontSee('Decline and refund');
});

it('shows the refund behind a declined parcel', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/orders/{$fulfillment->id}");

    $response->assertOk();
    $response->assertSee('The kiln cracked the glaze.');
    $response->assertSee('Seller');
});
