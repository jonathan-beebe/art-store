<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\Seller;

$paidFulfillment = function (Seller $seller): Fulfillment {
    $order = test()->orderFor(test()->verifiedCustomer(), test()->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', test()->moment('2026-08-20 10:00:00'));

    return Fulfillment::where('seller_id', $seller->id)->sole();
};

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
$form = fn (array $overrides = []): array => $overrides + ['carrier' => 'Royal Mail', 'tracking_number' => 'RM123'];

it('refuses a shipment the parcel cannot be traced by', function (array $overrides, string $field) use ($paidFulfillment, $form): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/shipment", $form($overrides));

    $response->assertSessionHasErrors($field);
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::AwaitingShipment);
})->with([
    'no carrier' => [['carrier' => ''], 'carrier'],
    'a carrier longer than the column' => [['carrier' => str_repeat('a', 256)], 'carrier'],
    'no tracking number' => [['tracking_number' => ''], 'tracking_number'],
    'a tracking number longer than the column' => [['tracking_number' => str_repeat('1', 256)], 'tracking_number'],
]);

it('answers another sellers fulfillment before it validates the form', function () use ($paidFulfillment): void {
    $fulfillment = $paidFulfillment($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/orders/{$fulfillment->id}/shipment", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::AwaitingShipment);
});

it('reads the carrier and tracking number the seller typed', function () use ($form): void {
    $request = MarkShippedRequest::create('/seller/orders/1/shipment', 'POST', $form());

    expect($request->carrier())->toBe('Royal Mail')
        ->and($request->trackingNumber())->toBe('RM123');
});
