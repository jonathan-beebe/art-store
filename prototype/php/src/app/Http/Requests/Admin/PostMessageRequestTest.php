<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;

it('refuses a reply longer than the message limit', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $this->seller()->id))
        ->create();

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('refuses an empty reply', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $this->seller()->id))
        ->create();

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

it('answers not found for a thread the admin is not in before it validates the form', function (): void {
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($this->admin()->id, $this->seller()->id))
        ->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/messages/{$conversation->id}", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the body the admin typed', function (): void {
    $request = PostMessageRequest::create('/admin/messages/1', 'POST', ['body' => "I'll take a look."]);

    expect($request->body()->value)->toBe("I'll take a look.");
});
