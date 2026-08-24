<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Refund;

it('refuses a decline with no reason the customer can read', function (array $form, string $field): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/decline", $form);

    $response->assertSessionHasErrors($field);
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::AwaitingShipment)
        ->and(Refund::count())->toBe(0);
})->with([
    'no reason' => [['reason' => ''], 'reason'],
    'a reason longer than the column' => [['reason' => str_repeat('a', 501)], 'reason'],
]);

it('answers another sellers fulfillment before it validates the form', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/orders/{$fulfillment->id}/decline", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::AwaitingShipment);
});

it('reads the reason the seller typed', function (): void {
    $request = DeclineFulfillmentRequest::create('/seller/orders/1/decline', 'POST', ['reason' => 'The kiln cracked it.']);

    expect($request->reason())->toBe('The kiln cracked it.');
});
