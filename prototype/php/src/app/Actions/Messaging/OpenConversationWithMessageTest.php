<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\MessageBody;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\CustomerBlock;
use App\Models\Message;
use Illuminate\Auth\Access\AuthorizationException;

it('opens the thread and posts the message that opens it', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);

    $conversation = app(OpenConversationWithMessage::class)(
        ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id),
        $customer,
        MessageBody::of('Does this ship framed?'),
        $this->moment('2026-08-20 10:00:00'),
    );

    expect($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->listing_id)->toBe($listing->id)
        ->and(Message::sole()->body)->toBe('Does this ship framed?')
        ->and($conversation->last_message_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00');
});

it('leaves no thread behind when the gate turns the sender down', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);
    $listing = $this->listing($seller);

    $ask = fn () => app(OpenConversationWithMessage::class)(
        ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id),
        $customer,
        MessageBody::of('Still available?'),
        $this->moment('2026-08-20 10:00:00'),
    );

    expect($ask)->toThrow(AuthorizationException::class)
        ->and(Conversation::count())->toBe(0)
        ->and(Message::count())->toBe(0);
});

it('finds the thread a second message about the same subject belongs to', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $subject = ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id);
    $ask = app(OpenConversationWithMessage::class);

    $first = $ask($subject, $customer, MessageBody::of('Does this ship framed?'), $this->moment('2026-08-20 10:00:00'));
    $second = $ask($subject, $customer, MessageBody::of('And is it signed?'), $this->moment('2026-08-20 11:00:00'));

    expect($second->id)->toBe($first->id)
        ->and(Conversation::count())->toBe(1)
        ->and(Message::count())->toBe(2);
});

it('opens an admin thread with a seller', function (): void {
    $admin = Admin::factory()->create();
    $seller = $this->seller();

    $conversation = app(OpenConversationWithMessage::class)(
        ConversationSubject::adminSeller($admin->id, $seller->id),
        $admin,
        MessageBody::of('Your payout ran this morning.'),
        $this->moment('2026-08-20 10:00:00'),
    );

    expect($conversation->admin_id)->toBe($admin->id)
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and(Message::sole()->sender_type)->toBe('admin');
});
