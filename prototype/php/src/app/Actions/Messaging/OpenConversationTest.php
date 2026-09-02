<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Models\Conversation;

it('opens a fulfillment conversation', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);
    $subject = ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id);

    $conversation = app(OpenConversation::class)($subject, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::Fulfillment)
        ->and($conversation->subject_key)->toBe($subject->subjectKey());
});

it('finds the same fulfillment conversation asking for the same subject twice', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);
    $subject = ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id);
    $openConversation = app(OpenConversation::class);

    $first = $openConversation($subject, $this->moment('2026-08-20 09:00:00'));
    $second = $openConversation($subject, $this->moment('2026-08-20 10:00:00'));

    expect($first->is($second))->toBeTrue()
        ->and(Conversation::count())->toBe(1);
});

it('opens a fresh, titled thread with no message from a ThreadOpening', function (): void {
    $seller = $this->seller();
    $opening = ThreadOpening::adminSeller($seller->id, ThreadTitle::of('Payout timing'));

    $conversation = app(OpenConversation::class)($opening, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::AdminSeller)
        ->and($conversation->title)->toBe('Payout timing')
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->admin_id)->toBeNull()
        ->and($conversation->subject_key)->toBeNull();
});

it('opens a second fresh thread rather than finding the first', function (): void {
    $seller = $this->seller();
    $openConversation = app(OpenConversation::class);
    $opening = fn () => ThreadOpening::adminSeller($seller->id, ThreadTitle::of('Payout timing'));

    $openConversation($opening(), $this->moment('2026-08-20 09:00:00'));
    $openConversation($opening(), $this->moment('2026-08-20 10:00:00'));

    expect(Conversation::count())->toBe(2);
});
