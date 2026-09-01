<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Orders\FinalizeOrder;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;

$awaitingShipment = function (Seller $seller, ?Customer $customer = null): Fulfillment {
    $order = test()->orderFor($customer ?? test()->verifiedCustomer(), test()->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', test()->moment('2026-08-20 10:00:00'));

    return $order->fulfillments()->sole();
};

it('lets the selling seller act on their own fulfillment', function (string $ability) use ($awaitingShipment): void {
    $seller = $this->seller();
    $fulfillment = $awaitingShipment($seller);

    $response = (new FulfillmentPolicy)->{$ability}($seller, $fulfillment);
    expect($response)->toBeInstanceOf(Response::class);

    /** @var Response $response */
    expect($response->allowed())->toBeTrue();
})->with(['view', 'update']);

it('answers not found for another sellers fulfillment', function (string $ability) use ($awaitingShipment): void {
    $fulfillment = $awaitingShipment($this->seller('Other Studio'));

    $response = (new FulfillmentPolicy)->{$ability}($this->seller(), $fulfillment);
    expect($response)->toBeInstanceOf(Response::class);

    /** @var Response $response */
    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
})->with(['view', 'update', 'ship', 'decline']);

it('offers the shipment form only while the fulfillment awaits shipment', function () use ($awaitingShipment): void {
    $seller = $this->seller();
    $policy = new FulfillmentPolicy;

    expect($policy->ship($seller, $awaitingShipment($seller))->allowed())->toBeTrue()
        ->and($policy->ship($seller, $this->shippedFulfillmentFor($seller))->allowed())->toBeFalse()
        ->and($policy->ship($seller, $this->deliveredFulfillmentFor($seller))->allowed())->toBeFalse();
});

it('offers the decline form only while the fulfillment awaits shipment', function () use ($awaitingShipment): void {
    $seller = $this->seller();
    $policy = new FulfillmentPolicy;

    expect($policy->decline($seller, $awaitingShipment($seller))->allowed())->toBeTrue()
        ->and($policy->decline($seller, $this->shippedFulfillmentFor($seller))->allowed())->toBeFalse()
        ->and($policy->decline($seller, $this->deliveredFulfillmentFor($seller))->allowed())->toBeFalse();
});

it('offers delivery confirmation to the buying customer only once shipped', function () use ($awaitingShipment): void {
    $shopper = $this->verifiedCustomer();
    $policy = new FulfillmentPolicy;

    expect($policy->confirmDelivery($shopper, $awaitingShipment($this->seller(), $shopper))->allowed())->toBeFalse()
        ->and($policy->confirmDelivery($shopper, $this->shippedFulfillmentFor($this->seller(), $shopper))->allowed())->toBeTrue()
        ->and($policy->confirmDelivery($shopper, $this->deliveredFulfillmentFor($this->seller(), $shopper))->allowed())->toBeFalse();
});

it('answers not found when another customer confirms delivery', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), $this->verifiedCustomer());

    $response = (new FulfillmentPolicy)->confirmDelivery($this->verifiedCustomer(), $fulfillment);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('refuses a state it cannot reach without hiding the row', function (): void {
    $seller = $this->seller();

    $response = (new FulfillmentPolicy)->ship($seller, $this->shippedFulfillmentFor($seller));

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBeNull();
});
