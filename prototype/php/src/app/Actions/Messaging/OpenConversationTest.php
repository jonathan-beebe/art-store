<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;

it('opens an admin/seller conversation', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $subject = ConversationSubject::adminSeller($admin->id, $seller->id);

    $conversation = app(OpenConversation::class)($subject, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::AdminSeller)
        ->and($conversation->subject_key)->toBe($subject->subjectKey());
});

it('opens an admin/customer conversation', function (): void {
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();
    $subject = ConversationSubject::adminCustomer($admin->id, $customer->id);

    $conversation = app(OpenConversation::class)($subject, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::AdminCustomer)
        ->and($conversation->subject_key)->toBe($subject->subjectKey());
});

it('opens a fulfillment conversation', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);
    $subject = ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id);

    $conversation = app(OpenConversation::class)($subject, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::Fulfillment)
        ->and($conversation->subject_key)->toBe($subject->subjectKey());
});

it('opens a listing question conversation', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $subject = ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id);

    $conversation = app(OpenConversation::class)($subject, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::ListingQuestion)
        ->and($conversation->subject_key)->toBe($subject->subjectKey());
});

it('finds the same conversation asking for the same subject twice', function (): void {
    $subject = ConversationSubject::listingQuestion(
        $this->seller()->id,
        $this->verifiedCustomer()->id,
        $this->listing($this->seller())->id,
    );
    $openConversation = app(OpenConversation::class);

    $first = $openConversation($subject, $this->moment('2026-08-20 09:00:00'));
    $second = $openConversation($subject, $this->moment('2026-08-20 10:00:00'));

    expect($first->is($second))->toBeTrue()
        ->and(Conversation::count())->toBe(1);
});
