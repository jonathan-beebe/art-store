<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Conversation;
use App\Models\Message;

it('refuses a reply longer than the message limit', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);

    $response = $this->post("/messages/{$conversation->id}", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('refuses an empty reply', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);

    $response = $this->post("/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

it('answers another visitors thread before it validates the form', function (): void {
    $this->visitor();
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->post("/messages/{$conversation->id}", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the body the visitor typed', function (): void {
    $request = PostMessageRequest::create('/messages/1', 'POST', ['body' => 'Thanks for the quick answer.']);

    expect($request->body()->value)->toBe('Thanks for the quick answer.');
});

it('quotes the named message when it belongs to the thread', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $visitor->id]);
    $original = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'It ships flat.']);

    $this->post("/messages/{$conversation->id}", ['body' => 'Sounds good.', 'reply_to_message_id' => $original->id]);

    expect(Message::where('body', 'Sounds good.')->sole()->reply_to_message_id)->toBe($original->id);
});

it('ignores a reply-to naming a message from another thread', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);
    $elsewhere = Message::factory()->create();

    $response = $this->post("/messages/{$conversation->id}", ['body' => 'Sounds good.', 'reply_to_message_id' => $elsewhere->id]);

    $response->assertRedirect(route('shop.messages.show', $conversation));
    expect(Message::where('body', 'Sounds good.')->sole()->reply_to_message_id)->toBeNull();
});

it('ignores a reply-to naming nothing at all', function (): void {
    $visitor = $this->arriveAs($this->verifiedCustomer());
    $conversation = Conversation::factory()->listingQuestion()->create(['customer_id' => $visitor->id]);

    $this->post("/messages/{$conversation->id}", ['body' => 'Sounds good.', 'reply_to_message_id' => 'msg_missing']);

    expect(Message::where('body', 'Sounds good.')->sole()->reply_to_message_id)->toBeNull();
});
