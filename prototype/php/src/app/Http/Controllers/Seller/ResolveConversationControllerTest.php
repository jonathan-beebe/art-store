<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Conversation;

it('marks an open thread the seller answers resolved', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}/resolve");

    $response->assertRedirect(route('seller.messages.show', [
        'conversation' => $conversation,
        'domain' => 'all',
    ]));
    $response->assertSessionHas('status', 'Marked resolved.');
    expect($conversation->fresh()?->resolved_at)->not->toBeNull();
});

it('carries the panes domain onward through the redirect', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}/resolve?domain=buyers");

    $response->assertRedirect(route('seller.messages.show', [
        'conversation' => $conversation,
        'domain' => 'buyers',
    ]));
});

it('refuses to resolve a thread already resolved', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    // The policy denies before the action's own no-op guard is ever reached:
    // `resolve` is allowed only when the thread does not already hold the
    // target status.
    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}/resolve");

    $response->assertForbidden();
});

it('refuses to resolve a kind the seller does not answer', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}/resolve");

    $response->assertForbidden();
    expect($conversation->fresh()?->resolved_at)->toBeNull();
});

it('answers not found for a thread the seller is not in', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/messages/{$conversation->id}/resolve");

    $response->assertNotFound();
});
