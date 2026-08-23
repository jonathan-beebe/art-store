<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Notification;
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

it('marks the fulfillment shipped', function () use ($paidFulfillment, $form): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/shipment", $form());

    $response->assertRedirect(route('seller.orders.show', $fulfillment->id));
    $shipped = $fulfillment->fresh();
    expect($shipped->status)->toBe(FulfillmentStatus::Shipped)
        ->and($shipped->carrier)->toBe('Royal Mail')
        ->and($shipped->tracking_number)->toBe('RM123')
        ->and($shipped->shipped_at)->not->toBeNull();
});

it('rolls the order up to shipped', function () use ($paidFulfillment, $form): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/shipment", $form());

    expect($fulfillment->order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('notifies the customer', function () use ($paidFulfillment, $form): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/shipment", $form());

    expect(Notification::where('customer_id', $fulfillment->order->customer_id)->count())->toBe(1);
});

it('rejects a shipment without a carrier', function () use ($paidFulfillment, $form): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/shipment", $form(['carrier' => '']));

    $response->assertSessionHasErrors('carrier');
    expect($fulfillment->fresh()->status)->toBe(FulfillmentStatus::AwaitingShipment);
});

it('rejects a shipment without a tracking number', function () use ($paidFulfillment, $form): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/shipment", $form(['tracking_number' => '']));

    $response->assertSessionHasErrors('tracking_number');
});

it('refuses to ship a fulfillment that already shipped', function () use ($paidFulfillment, $form): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller);
    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/shipment", $form());

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/shipment", $form(['tracking_number' => 'RM999']));

    $response->assertStatus(422);
    expect($fulfillment->fresh()->tracking_number)->toBe('RM123');
});

it('refuses to ship another sellers fulfillment', function () use ($paidFulfillment, $form): void {
    $fulfillment = $paidFulfillment($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/orders/{$fulfillment->id}/shipment", $form());

    $response->assertNotFound();
    expect($fulfillment->fresh()->status)->toBe(FulfillmentStatus::AwaitingShipment);
});
