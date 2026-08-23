<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Fulfillment;

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
