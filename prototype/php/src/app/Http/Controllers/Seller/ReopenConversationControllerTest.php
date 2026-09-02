<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Conversation;

it('reopens a resolved thread', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}/reopen");

    $response->assertRedirect(route('seller.messages.show', ['conversation' => $conversation, 'filter' => 'all', 'status' => 'open']));
    $response->assertSessionHas('status', 'Reopened.');
    expect($conversation->fresh()?->resolved_at)->toBeNull();
});

it('carries the panes filter and status onward through the redirect', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}/reopen?filter=support&status=resolved");

    $response->assertRedirect(route('seller.messages.show', ['conversation' => $conversation, 'filter' => 'support', 'status' => 'resolved']));
});

it('refuses to reopen a thread already open', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    // The policy denies before the action's own no-op guard is ever reached:
    // `reopen` is allowed only when the thread does not already hold the
    // target status.
    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}/reopen");

    $response->assertForbidden();
});

it('answers not found for a thread the seller is not in', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create([
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/messages/{$conversation->id}/reopen");

    $response->assertNotFound();
});
