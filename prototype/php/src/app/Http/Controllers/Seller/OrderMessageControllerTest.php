<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\Fulfillment;
use Illuminate\Support\Facades\Config;

it('opens the thread for the order and the customer and lands on it', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/messages");

    $conversation = Conversation::sole();
    expect($conversation->fulfillment_id)->toBe($fulfillment->id)
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($customer->id)
        // The key the customer's own route for this order asks for, so both
        // sides of the thread find the one row.
        ->and($conversation->subject_key)
        ->toBe(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id)->subjectKey());
    $response->assertRedirect(route('seller.messages.show', $conversation));
});

it('lands on the same thread a second time', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/messages");

    $this->actingAs($seller, 'seller')->post("/seller/orders/{$fulfillment->id}/messages");

    expect(Conversation::count())->toBe(1);
});

it('refuses to message about another sellers fulfillment', function (): void {
    $other = $this->seller('Other Studio');
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($other));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $other->id)->sole();

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/orders/{$fulfillment->id}/messages");

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});

it('trips the conversation-open limit before opening a second fulfillment thread', function (): void {
    Config::set('rate_limits.conversation_open', RateLimitValue::parse('1/1h', 'RATE_LIMIT_CONVERSATION_OPEN'));
    $seller = $this->seller();
    $firstOrder = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($firstOrder, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $firstFulfillment = Fulfillment::where('order_id', $firstOrder->id)->sole();
    $secondOrder = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($secondOrder, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $secondFulfillment = Fulfillment::where('order_id', $secondOrder->id)->sole();
    $this->actingAs($seller, 'seller')->post("/seller/orders/{$firstFulfillment->id}/messages");

    $response = $this->actingAs($seller, 'seller')->post("/seller/orders/{$secondFulfillment->id}/messages");

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    expect(Conversation::count())->toBe(1);
});
