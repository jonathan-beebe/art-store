<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Messaging\ConversationKind;
use App\Models\Conversation;
use App\Models\Customer;

it('opens the buyer\'s newest thread when there is one', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood']);
    $this->paidFulfillmentFor($seller, $customer);

    Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'last_message_at' => $this->moment('2026-08-20 09:00:00'),
    ]);
    $newest = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'last_message_at' => $this->moment('2026-08-25 09:00:00'),
    ]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/customers/{$customer->id}/messages");

    $response->assertRedirect(route('seller.messages.show', $newest));
});

it('opens the thread for the buyer\'s latest parcel when they have never written', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer);

    $response = $this->actingAs($seller, 'seller')->post("/seller/customers/{$customer->id}/messages");

    $conversation = Conversation::query()->sole();

    expect($conversation->kind)->toBe(ConversationKind::Fulfillment)
        ->and($conversation->fulfillment_id)->toBe($fulfillment->id)
        ->and($conversation->customer_id)->toBe($customer->id);

    $response->assertRedirect(route('seller.messages.show', $conversation));
});

it('answers 404 for a customer who has never bought from this seller', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $visitor = Customer::factory()->create(['name' => 'Draco Malfoy']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/customers/{$visitor->id}/messages");

    $response->assertNotFound();
    expect(Conversation::query()->count())->toBe(0);
});

it('sends a signed-out visitor to sign in', function (): void {
    $customer = Customer::factory()->create();

    $this->post("/seller/customers/{$customer->id}/messages")->assertRedirect(route('auth.seller.login'));
});
