<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Conversation;

it('marks a desk thread resolved', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create();

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}/resolve");

    $response->assertRedirect(route('admin.messages.show', $conversation));
    expect($conversation->fresh()?->resolved_at)->not->toBeNull();
});

it('refuses to resolve an oversight thread, the desk never owns it', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->fulfillment()->create();

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}/resolve");

    $response->assertForbidden();
});

it('refuses to resolve a thread that is already resolved', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create(['resolved_at' => now()]);

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}/resolve");

    $response->assertForbidden();
});
