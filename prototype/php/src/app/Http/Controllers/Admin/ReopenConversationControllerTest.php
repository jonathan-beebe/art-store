<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Conversation;

it('reopens a resolved desk thread', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create(['resolved_at' => now()]);

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}/reopen");

    $response->assertRedirect(route('admin.messages.show', ['conversation' => $conversation, 'filter' => 'all', 'status' => 'all']));
    expect($conversation->fresh()?->resolved_at)->toBeNull();
});

it('carries the panes filter and status onward through the redirect', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create(['resolved_at' => now()]);

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}/reopen?filter=customers&status=resolved");

    $response->assertRedirect(route('admin.messages.show', ['conversation' => $conversation, 'filter' => 'customers', 'status' => 'resolved']));
});

it('refuses to reopen a thread that is not resolved', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create();

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}/reopen");

    $response->assertForbidden();
});
