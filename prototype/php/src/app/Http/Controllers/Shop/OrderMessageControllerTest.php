<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;

it('opens the thread for the order and the seller and lands on it', function (): void {
    $seller = $this->seller();
    $customer = $this->arriveAs($this->verifiedCustomer());
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);

    $response = $this->post("/orders/{$fulfillment->order_id}/fulfillments/{$fulfillment->id}/messages");

    $conversation = Conversation::sole();
    expect($conversation->fulfillment_id)->toBe($fulfillment->id)
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($customer->id)
        // The key the seller's own route for this order asks for, so both
        // sides of the thread find the one row.
        ->and($conversation->subject_key)
        ->toBe(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id)->subjectKey());
    $response->assertRedirect(route('shop.messages.show', $conversation));
});

it('lands on the same thread a second time', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), $customer);
    $this->post("/orders/{$fulfillment->order_id}/fulfillments/{$fulfillment->id}/messages");

    $this->post("/orders/{$fulfillment->order_id}/fulfillments/{$fulfillment->id}/messages");

    expect(Conversation::count())->toBe(1);
});

it('refuses to message about another visitors order', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), $this->verifiedCustomer());
    $this->visitor();

    $response = $this->post("/orders/{$fulfillment->order_id}/fulfillments/{$fulfillment->id}/messages");

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});

it('refuses a fulfillment that belongs to another order', function (): void {
    $customer = $this->arriveAs($this->verifiedCustomer());
    $mine = $this->shippedFulfillmentFor($this->seller(), $customer);
    $theirs = $this->shippedFulfillmentFor($this->seller('Other Studio'), $customer);

    $response = $this->post("/orders/{$mine->order_id}/fulfillments/{$theirs->id}/messages");

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});
