<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\PostMessage;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\MessageBody;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\Message;

it('lists the sellers threads newest first with who, what, and unread count', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);
    $older = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id))
        ->create(['last_message_at' => $this->moment('2026-08-20 09:00:00')]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $older->id, 'body' => 'Is this framed?']);

    $newerCustomer = $this->verifiedCustomer();
    $newer = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $newerCustomer->id, $listing->id))
        ->create(['last_message_at' => $this->moment('2026-08-21 09:00:00')]);
    Message::factory()->from($newerCustomer)->unread()->create(['conversation_id' => $newer->id, 'body' => 'Do you ship to France?']);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertSeeInOrder(['Do you ship to France?', 'Is this framed?']);
    $response->assertSee('Harbour at Dusk');
    $response->assertSee('1 unread');
});

it('keeps another sellers threads off the inbox', function (): void {
    $listing = $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $listing->seller_id, 'listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertDontSee('Not Mine');
});

it('names an order thread and a support thread on the inbox', function (): void {
    $seller = $this->seller();
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    Conversation::factory()
        ->forSubject(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id))
        ->create();
    Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertSee("Order #{$fulfillment->order_id}");
    $response->assertSee($admin->displayName());
});

it('renders the inbox on a fixed number of queries however many threads the seller holds', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    foreach (range(1, 5) as $ignored) {
        $customer = $this->verifiedCustomer();
        $conversation = Conversation::factory()
            ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id))
            ->create();
        Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);
    }

    $response = $this->actingAs($seller, 'seller')
        ->expectsDatabaseQueryCount(6)
        ->get('/seller/messages');

    $response->assertOk();
});

it('shows every message in order and marks the thread read', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $this->listing($seller)->id))
        ->create();
    $first = Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id, 'body' => 'Is this framed?']);
    $second = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Not yet.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSeeInOrder(['Is this framed?', 'Not yet.']);
    expect($first->fresh()?->read_at)->not->toBeNull()
        ->and($second->fresh()?->read_at)->toBeNull();
});

it('answers not found for a thread the seller is not in', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertNotFound();
});

it('answers not found for a thread id that matches nothing', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages/999999');

    $response->assertNotFound();
});

it('appends a reply and returns to the thread with it visible', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => 'It ships within 3 days.']);

    $response->assertRedirect(route('seller.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'It ships within 3 days.')->exists())->toBeTrue();
    $this->actingAs($seller, 'seller')
        ->get(route('seller.messages.show', $conversation))
        ->assertSee('It ships within 3 days.');
});

it('leaves the thread unread when the reply is refused', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $this->listing($seller)->id))
        ->create();
    $question = Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
    expect($question->fresh()?->read_at)->toBeNull();
});

it('answers not found replying to a thread the seller is not in', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => 'Sneaking in.']);

    $response->assertNotFound();
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Sneaking in.')->exists())->toBeFalse();
});

it('offers publish as faq prefilled from the question and the sellers latest answer, only for a listing thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id))
        ->create();
    Message::factory()->from($customer)->create(['conversation_id' => $conversation->id, 'body' => 'Is this framed?']);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Yes, framed in black wood.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertSee('Publish as FAQ');
    $response->assertSee('value="Is this framed?"', escape: false);
    $response->assertSee('Yes, framed in black wood.');
});

it('offers no publish as faq section for a support thread', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertDontSee('Publish as FAQ');
});

it('moves the thread to the top of the inbox after a reply', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $this->listing($seller)->id))
        ->create(['last_message_at' => $this->moment('2026-08-01 09:00:00')]);
    app(PostMessage::class)($conversation, $customer, MessageBody::of('Is this framed?'), $this->moment('2026-08-01 09:00:00'));

    $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", ['body' => 'Not yet framed.']);

    expect($conversation->fresh()?->last_message_at?->greaterThan($this->moment('2026-08-01 09:00:00')))->toBeTrue();
});
