<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Messaging\MarkConversationRead;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Message;

it('counts the messages across every thread the admin has not read', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($admin)->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($admin, 'admin')->get('/admin');

    $response->assertSee('Messages (1)', escape: false);
});

it('drops the count once the thread is marked read', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);
    app(MarkConversationRead::class)($conversation, $admin, $this->moment('2026-08-20 09:00:00'));

    $response = $this->actingAs($admin, 'admin')->get('/admin');

    $response->assertDontSee('Messages (1)', escape: false);
});

it('carries the count onto every admin page without the controller passing it', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);

    $this->actingAs($admin, 'admin')->get('/admin/sellers')->assertSee('Messages (1)', escape: false);
    $this->actingAs($admin, 'admin')->get('/admin/customers')->assertSee('Messages (1)', escape: false);
});

it('renders a page with no admin signed in without the count', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertDontSee('Messages (', escape: false);
});
