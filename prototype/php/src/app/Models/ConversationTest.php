<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationSubject;

it('opens a conversation for a subject not seen before', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $subject = ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id);

    $conversation = Conversation::openFor($subject, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::ListingQuestion)
        ->and($conversation->subject_key)->toBe($subject->subjectKey())
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->listing_id)->toBe($listing->id)
        ->and(Conversation::count())->toBe(1);
});

it('finds the same conversation for the same subject asked twice', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $subject = ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id);

    $first = Conversation::openFor($subject, $this->moment('2026-08-20 09:00:00'));
    $second = Conversation::openFor($subject, $this->moment('2026-08-20 10:00:00'));

    expect($first->is($second))->toBeTrue()
        ->and(Conversation::count())->toBe(1);
});

it('reads which id belongs to a given actor type', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);

    expect($conversation->participantIdFor(ActorType::Seller))->toBe($seller->id)
        ->and($conversation->participantIdFor(ActorType::Customer))->toBe($customer->id)
        ->and($conversation->participantIdFor(ActorType::Admin))->toBeNull();
});

it('names the other participant a posted message goes to', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $fromCustomer = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);
    $fromSeller = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->otherParticipant($fromCustomer)?->is($seller))->toBeTrue()
        ->and($conversation->otherParticipant($fromSeller)?->is($customer))->toBeTrue();
});

it('has no other participant when the thread is missing one side', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => null,
    ]);
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->otherParticipant($message))->toBeNull();
});

it('names the counterpart a viewer sees, not the viewer\'s own side', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->counterpart(ActorType::Seller)?->is($customer))->toBeTrue()
        ->and($conversation->counterpart(ActorType::Customer)?->is($seller))->toBeTrue();
});

it('has no counterpart when the thread is missing that side', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => null,
    ]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->counterpart(ActorType::Seller))->toBeNull();
});

it('reads a customer counterpart by name, falling back to their id', function (): void {
    $seller = $this->seller();
    $named = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => Customer::factory()->create(['name' => 'Ada Lovelace'])->id,
    ]);
    $unnamed = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => Customer::factory()->create(['name' => null])->id,
    ]);
    $named->load(['seller', 'customer', 'admin']);
    $unnamed->load(['seller', 'customer', 'admin']);

    expect($named->counterpartName(ActorType::Seller))->toBe('Ada Lovelace')
        ->and($unnamed->counterpartName(ActorType::Seller))->toBe('Customer #'.$unnamed->customer_id);
});

it('reads an admin or seller counterpart by their display name', function (): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');
    $conversation = Conversation::factory()->adminSeller()->create([
        'admin_id' => $admin->id,
        'seller_id' => $seller->id,
    ]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->counterpartName(ActorType::Seller))->toBe($admin->displayName())
        ->and($conversation->counterpartName(ActorType::Admin))->toBe('Blue Kiln Studio');
});

it('names no counterpart account as deleted', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => null,
    ]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->counterpartName(ActorType::Seller))->toBe('Deleted account');
});

it('reads the newest message as the preview', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();
    Message::factory()->create(['conversation_id' => $conversation->id, 'sent_at' => now()->subMinute()]);
    $newest = Message::factory()->create(['conversation_id' => $conversation->id, 'sent_at' => now()]);

    expect($conversation->latestMessage?->is($newest))->toBeTrue();
});

it('prefills an faq from the opening question and the seller\'s latest answer', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $conversation->id, 'body' => 'Is this framed?']);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Not yet framed.']);
    $latestAnswer = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Yes, framed in black wood.']);
    $conversation->load('messages');

    $prefill = $conversation->faqPrefill();

    expect($prefill?->question)->toBe('Is this framed?')
        ->and($prefill?->answer)->toBe('Yes, framed in black wood.')
        ->and($prefill?->sourceMessageId)->toBe($latestAnswer->id);
});

it('offers no faq prefill for a thread the seller has not answered', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();
    Message::factory()->create(['conversation_id' => $conversation->id]);
    $conversation->load('messages');

    expect($conversation->faqPrefill())->toBeNull();
});

it('offers no faq prefill for a thread with no listing', function (): void {
    $conversation = Conversation::factory()->adminSeller()->create();
    $conversation->load('messages');

    expect($conversation->faqPrefill())->toBeNull();
});

it('scopes threads to the given participant', function (): void {
    $seller = $this->seller();
    $mine = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    Conversation::factory()->listingQuestion()->create();

    expect(Conversation::query()->withParticipant($seller)->pluck('id')->all())->toBe([$mine->id]);
});

it('counts the messages a reader has not read on each thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    Message::factory()->from($customer)->unread()->count(2)->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($customer)->read()->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);

    $counted = Conversation::query()->withUnreadCountFor($seller)->findOrFail($conversation->id);

    expect($counted->unread_count)->toBe(2);
});

it('moves a thread to another customer, key and column together', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $conversation = Conversation::openFor(
        ConversationSubject::listingQuestion($seller->id, $anonymous->id, $listing->id),
        $this->moment('2026-08-20 09:00:00'),
    );

    Conversation::moveCustomer($anonymous, $verified);

    expect($conversation->fresh()?->customer_id)->toBe($verified->id)
        ->and($conversation->fresh()?->subject_key)
        ->toBe(ConversationSubject::listingQuestion($seller->id, $verified->id, $listing->id)->subjectKey());
});

it('folds a moved thread into the one the other customer already holds', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $held = Conversation::openFor(
        ConversationSubject::listingQuestion($seller->id, $verified->id, $listing->id),
        $this->moment('2026-08-01 09:00:00'),
    );
    $moved = Conversation::openFor(
        ConversationSubject::listingQuestion($seller->id, $anonymous->id, $listing->id),
        $this->moment('2026-08-02 09:00:00'),
    );
    $message = Message::factory()->from($anonymous)->create([
        'conversation_id' => $moved->id,
        'sent_at' => $this->moment('2026-08-02 09:00:00'),
    ]);

    Conversation::moveCustomer($anonymous, $verified);

    expect(Conversation::count())->toBe(1)
        ->and($message->fresh()?->conversation_id)->toBe($held->id)
        ->and($held->fresh()?->last_message_at?->format('Y-m-d H:i:s'))->toBe('2026-08-02 09:00:00');
});

it('leaves the surviving thread when neither side carries a message', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $held = Conversation::openFor(
        ConversationSubject::listingQuestion($seller->id, $verified->id, $listing->id),
        $this->moment('2026-08-01 09:00:00'),
    );
    Conversation::openFor(
        ConversationSubject::listingQuestion($seller->id, $anonymous->id, $listing->id),
        $this->moment('2026-08-02 09:00:00'),
    );

    Conversation::moveCustomer($anonymous, $verified);

    expect(Conversation::count())->toBe(1)
        ->and($held->fresh()?->last_message_at?->format('Y-m-d H:i:s'))->toBe('2026-08-01 09:00:00');
});
