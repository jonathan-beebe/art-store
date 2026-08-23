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

it('scopes threads to the given participant', function (): void {
    $seller = $this->seller();
    $mine = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    Conversation::factory()->listingQuestion()->create();

    expect(Conversation::query()->withParticipant($seller)->pluck('id')->all())->toBe([$mine->id]);
});
