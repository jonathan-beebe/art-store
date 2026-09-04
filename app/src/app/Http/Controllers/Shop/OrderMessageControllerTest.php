<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use Illuminate\Support\Facades\Config;

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

it('trips the conversation-open limit before opening a second fulfillment thread', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $customer = $this->arriveAs($this->verifiedCustomer());
    $first = $this->shippedFulfillmentFor($this->seller('Blue Kiln Studio'), $customer);
    $second = $this->shippedFulfillmentFor($this->seller('Rye Press'), $customer);
    $this->post("/orders/{$first->order_id}/fulfillments/{$first->id}/messages");

    $response = $this->post("/orders/{$second->order_id}/fulfillments/{$second->id}/messages");

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    expect(Conversation::count())->toBe(1);
});
