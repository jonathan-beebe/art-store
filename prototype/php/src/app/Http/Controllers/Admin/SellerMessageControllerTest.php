<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Message;

it('opens the sellers admin thread, posts the message, and lands on it', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/sellers/{$seller->id}/messages", ['body' => 'Please review your listing photos.']);

    $conversation = Conversation::sole();
    expect($conversation->subject_key)
        ->toBe(ConversationSubject::adminSeller($admin->id, $seller->id)->subjectKey());
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Please review your listing photos.')->exists())->toBeTrue();
    $response->assertRedirect(route('admin.messages.show', $conversation));
});

it('lands on the same thread a second time', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $this->actingAs($admin, 'admin')->post("/admin/sellers/{$seller->id}/messages", ['body' => 'First message.']);

    $this->actingAs($admin, 'admin')->post("/admin/sellers/{$seller->id}/messages", ['body' => 'Second message.']);

    expect(Conversation::count())->toBe(1)
        ->and(Message::where('conversation_id', Conversation::sole()->id)->count())->toBe(2);
});

it('refuses a message longer than the message limit', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/sellers/{$seller->id}/messages", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
    expect(Conversation::count())->toBe(0);
});

it('sends a guest to the admin login page', function (): void {
    $seller = $this->seller();

    $response = $this->post("/admin/sellers/{$seller->id}/messages", ['body' => 'Please review your listing photos.']);

    $response->assertRedirect(route('auth.admin.login'));
});
