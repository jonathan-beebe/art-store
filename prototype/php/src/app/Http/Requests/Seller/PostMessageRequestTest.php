<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Conversation;
use App\Models\Message;

it('refuses a reply longer than the message limit', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('refuses an empty reply', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

it('answers another sellers thread before it validates the form', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/messages/{$conversation->id}", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the body the seller typed', function (): void {
    $request = PostMessageRequest::create('/seller/messages/1', 'POST', ['body' => 'It ships within 3 days.']);

    expect($request->body()->value)->toBe('It ships within 3 days.');
});

it('reads no reply-to when none was submitted', function (): void {
    $request = PostMessageRequest::create('/seller/messages/1', 'POST', ['body' => 'It ships within 3 days.']);

    expect($request->replyTo())->toBeNull();
});

it('ignores a reply-to naming a message from another thread, rather than refusing the reply', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    $otherThreadMessage = Message::factory()->create();

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", [
        'body' => 'It ships within 3 days.',
        'reply_to_message_id' => $otherThreadMessage->id,
    ]);

    $response->assertRedirect(route('seller.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'It ships within 3 days.')->sole()->reply_to_message_id)
        ->toBeNull();
});

it('ignores a reply-to naming no message at all, rather than refusing the reply', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", [
        'body' => 'It ships within 3 days.',
        'reply_to_message_id' => 'msg_does_not_exist',
    ]);

    $response->assertRedirect(route('seller.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'It ships within 3 days.')->sole()->reply_to_message_id)
        ->toBeNull();
});

it('reads a reply-to naming a message of the same thread', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    $quoted = Message::factory()->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", [
        'body' => 'Yes, it does.',
        'reply_to_message_id' => $quoted->id,
    ]);

    $response->assertRedirect(route('seller.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Yes, it does.')->sole()->reply_to_message_id)
        ->toBe($quoted->id);
});
