<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\CustomerBlock;
use App\Models\Message;

it('lists the visitors threads newest first with who, what, and unread count', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $seller = $this->seller('Blue Kiln Studio');
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);
    $older = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $visitor->id, $listing->id))
        ->create(['last_message_at' => $this->moment('2026-08-20 09:00:00')]);
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $older->id, 'body' => 'It ships flat.']);

    $newerListing = $this->listing($this->seller('Rye Press'), ['title' => 'Winter Elm']);
    $newer = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($newerListing->seller_id, $visitor->id, $newerListing->id))
        ->create(['last_message_at' => $this->moment('2026-08-21 09:00:00')]);
    Message::factory()->from($newerListing->seller)->unread()->create(['conversation_id' => $newer->id, 'body' => 'Yes, worldwide.']);

    $response = $this->get('/messages');

    $response->assertOk();
    $response->assertSeeInOrder(['Yes, worldwide.', 'It ships flat.']);
    $response->assertSee('Winter Elm');
    $response->assertSee('1 unread');
});

it('keeps another visitors threads off the inbox', function (): void {
    $listing = $this->listing($this->seller());
    Conversation::factory()->listingQuestion()->create(['seller_id' => $listing->seller_id, 'listing_id' => $listing->id]);

    $response = $this->get('/messages');

    $response->assertOk();
    $response->assertDontSee($listing->title);
});

it('shows every message in order and marks the thread read', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $visitor->id, $this->listing($seller)->id))
        ->create();
    $first = Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id, 'body' => 'It ships flat.']);
    $second = Message::factory()->from($visitor)->create(['conversation_id' => $conversation->id, 'body' => 'Thanks!']);

    $response = $this->get("/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSeeInOrder(['It ships flat.', 'Thanks!']);
    expect($first->fresh()?->read_at)->not->toBeNull()
        ->and($second->fresh()?->read_at)->toBeNull();
});

it('answers not found for a thread the visitor is not in', function (): void {
    $this->visitor();
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->get("/messages/{$conversation->id}");

    $response->assertNotFound();
});

it('answers not found for a thread id that matches nothing', function (): void {
    $this->visitor();

    $response = $this->get('/messages/999999');

    $response->assertNotFound();
});

it('appends a reply and returns to the thread with it visible', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);

    $response = $this->post("/messages/{$conversation->id}", ['body' => 'Sounds good, thank you.']);

    $response->assertRedirect(route('shop.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Sounds good, thank you.')->exists())->toBeTrue();
    $this->get(route('shop.messages.show', $conversation))->assertSee('Sounds good, thank you.');
});

it('refuses an empty reply', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);

    $response = $this->post("/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

it('answers not found replying to a thread the visitor is not in', function (): void {
    $this->visitor();
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->post("/messages/{$conversation->id}", ['body' => 'Sneaking in.']);

    $response->assertNotFound();
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Sneaking in.')->exists())->toBeFalse();
});

it('reads a thread with no reply form while blocked, and refuses a hand-rolled reply', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    CustomerBlock::factory()->create(['customer_id' => $visitor->id]);
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $visitor->id]);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Still there?']);

    $show = $this->get("/messages/{$conversation->id}");
    $show->assertOk();
    $show->assertDontSee('name="body"', escape: false);

    $reply = $this->post("/messages/{$conversation->id}", ['body' => 'Trying anyway.']);

    $reply->assertForbidden();
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Trying anyway.')->exists())->toBeFalse();
});
