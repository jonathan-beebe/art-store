<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Messaging\MarkConversationRead;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Message;

it('counts the messages across every thread the seller has not read', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id))
        ->create();
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertSee('Messages (1)', escape: false);
});

it('drops the count once the thread is marked read', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);
    app(MarkConversationRead::class)($conversation, $seller, $this->moment('2026-08-20 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertDontSee('Messages (1)', escape: false);
});

it('carries the count onto every seller page without the controller passing it', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $this->actingAs($seller, 'seller')->get('/seller/listings')->assertSee('Messages (1)', escape: false);
    $this->actingAs($seller, 'seller')->get('/seller/orders')->assertSee('Messages (1)', escape: false);
});

it('renders a page with no seller signed in without the count', function (): void {
    $response = $this->get('/seller/login');

    $response->assertOk();
    $response->assertDontSee('Messages (', escape: false);
});
