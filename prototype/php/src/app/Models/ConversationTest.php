<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\Messaging\ConversationSubject;
use App\Support\ActorDisplay;

it('opens a fulfillment conversation for a subject not seen before', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);
    $subject = ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id);

    $conversation = Conversation::openFor($subject, $this->moment('2026-08-20 09:00:00'));

    expect($conversation->kind)->toBe(ConversationKind::Fulfillment)
        ->and($conversation->subject_key)->toBe($subject->subjectKey())
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->customer_id)->toBe($customer->id)
        ->and($conversation->fulfillment_id)->toBe($fulfillment->id)
        ->and(Conversation::count())->toBe(1);
});

it('finds the same fulfillment conversation for the same subject asked twice', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);
    $subject = ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id);

    $first = Conversation::openFor($subject, $this->moment('2026-08-20 09:00:00'));
    $second = Conversation::openFor($subject, $this->moment('2026-08-20 10:00:00'));

    expect($first->is($second))->toBeTrue()
        ->and(Conversation::count())->toBe(1);
});

it('names its order for an admin/customer thread', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));
    $conversation = Conversation::factory()->adminCustomer()->create(['customer_id' => $customer->id, 'order_id' => $order->id]);

    expect($conversation->order?->is($order))->toBeTrue();
});

it('reads open and resolved from resolved_at', function (): void {
    $open = Conversation::factory()->listingQuestion()->create();
    $resolved = Conversation::factory()->listingQuestion()->create(['resolved_at' => now()]);

    expect($open->status())->toBe(ConversationStatus::Open)
        ->and($resolved->status())->toBe(ConversationStatus::Resolved);
});

it('names who resolved it through the resolvedBy morph', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'resolved_at' => now(),
        'resolved_by_type' => $seller->getMorphClass(),
        'resolved_by_id' => $seller->id,
    ]);

    expect($conversation->resolvedBy?->is($seller))->toBeTrue();
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

it('sends a posted message to every admin when the desk is the other side', function (): void {
    $firstAdmin = Admin::factory()->create();
    $secondAdmin = Admin::factory()->create();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    $conversation->load(['seller', 'customer', 'admin']);

    $recipients = $conversation->recipientsOf($message);

    expect($recipients->pluck('id')->sort()->values()->all())
        ->toBe(collect([$firstAdmin->id, $secondAdmin->id])->sort()->values()->all());
});

it('sends a desk reply to the single seller or customer, not the desk', function (): void {
    Admin::factory()->create();
    $admin = Admin::factory()->create();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'admin_id' => $admin->id]);
    $message = Message::factory()->from($admin)->create(['conversation_id' => $conversation->id]);
    $conversation->load(['seller', 'customer', 'admin']);

    $recipients = $conversation->recipientsOf($message);

    expect($recipients->count())->toBe(1)
        ->and($recipients->first()?->is($seller))->toBeTrue();
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
        ->and($unnamed->counterpartName(ActorType::Seller))->toBe('Customer '.$unnamed->customer_id);
});

it('names the desk to a seller or a customer on a support thread, even before any admin has answered', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->counterpartName(ActorType::Seller))->toBe(ActorDisplay::SUPPORT_DESK);
});

it('names the seller to the admin on a support thread', function (): void {
    $admin = Admin::factory()->create();
    $seller = $this->seller('Blue Kiln Studio');
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'admin_id' => $admin->id]);
    $conversation->load(['seller', 'customer', 'admin']);

    expect($conversation->counterpartName(ActorType::Admin))->toBe('Blue Kiln Studio');
});

it('names both sides to the admin on an oversight thread', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $customer = Customer::factory()->create(['name' => 'Hermione Granger']);
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    $conversation->load(['seller', 'customer']);

    expect($conversation->counterpartName(ActorType::Admin))->toBe('Blue Kiln Studio ↔ Hermione Granger');
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

it('scopes both support kinds to any admin, regardless of admin_id', function (): void {
    $firstAdmin = Admin::factory()->create();
    $secondAdmin = Admin::factory()->create();
    $sellerThread = Conversation::factory()->adminSeller()->create(['admin_id' => $firstAdmin->id]);
    $customerThread = Conversation::factory()->adminCustomer()->create();
    Conversation::factory()->listingQuestion()->create();
    Conversation::factory()->fulfillment()->create();

    expect(Conversation::query()->withParticipant($secondAdmin)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$sellerThread->id, $customerThread->id])->sort()->values()->all());
});

it('lists seller/customer threads for oversight, never a desk thread', function (): void {
    $fulfillment = Conversation::factory()->fulfillment()->create();
    $question = Conversation::factory()->listingQuestion()->create();
    Conversation::factory()->adminSeller()->create();

    expect(Conversation::query()->forOversight()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$fulfillment->id, $question->id])->sort()->values()->all());
});

it('filters threads by status', function (): void {
    $open = Conversation::factory()->listingQuestion()->create();
    $resolved = Conversation::factory()->listingQuestion()->create(['resolved_at' => now()]);

    expect(Conversation::query()->withStatus(ConversationStatus::Open)->pluck('id')->all())->toBe([$open->id])
        ->and(Conversation::query()->withStatus(ConversationStatus::Resolved)->pluck('id')->all())->toBe([$resolved->id]);
});

it('filters threads by kind', function (): void {
    $question = Conversation::factory()->listingQuestion()->create();
    Conversation::factory()->fulfillment()->create();

    expect(Conversation::query()->ofKind(ConversationKind::ListingQuestion)->pluck('id')->all())->toBe([$question->id]);
});

it('lists only threads carrying an unread message for the reader', function (): void {
    $seller = $this->seller();
    $customerA = $this->verifiedCustomer();
    $customerB = $this->verifiedCustomer();
    $unread = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customerA->id]);
    Message::factory()->from($customerA)->unread()->create(['conversation_id' => $unread->id]);
    $read = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customerB->id]);
    Message::factory()->from($customerB)->read()->create(['conversation_id' => $read->id]);

    expect(Conversation::query()->unreadOnly($seller)->pluck('id')->all())->toBe([$unread->id]);
});

it('orders the desk\'s needs-reply queue to open threads awaiting a non-admin reply', function (): void {
    $admin = Admin::factory()->create();
    $seller = $this->seller();
    $awaiting = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    Message::factory()->from($seller)->create(['conversation_id' => $awaiting->id]);
    $answered = Conversation::factory()->adminSeller()->create();
    Message::factory()->from($admin)->create(['conversation_id' => $answered->id]);
    $resolved = Conversation::factory()->adminSeller()->create(['resolved_at' => now()]);
    Message::factory()->create(['conversation_id' => $resolved->id]);
    Conversation::factory()->listingQuestion()->create();

    expect(Conversation::query()->needsReply()->pluck('id')->all())->toBe([$awaiting->id]);
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

it('moves a fresh-opened thread to another customer by column alone', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $anonymous->id,
        'listing_id' => $listing->id,
    ]);

    Conversation::moveCustomer($anonymous, $verified);

    expect($conversation->fresh()?->customer_id)->toBe($verified->id)
        ->and($conversation->fresh()?->subject_key)->toBeNull();
});

it('moves a thread to another customer, key and column together, for a fulfillment thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $conversation = Conversation::openFor(
        ConversationSubject::fulfillment($seller->id, $anonymous->id, $fulfillment->id),
        $this->moment('2026-08-20 09:00:00'),
    );

    Conversation::moveCustomer($anonymous, $verified);

    expect($conversation->fresh()?->customer_id)->toBe($verified->id)
        ->and($conversation->fresh()?->subject_key)
        ->toBe(ConversationSubject::fulfillment($seller->id, $verified->id, $fulfillment->id)->subjectKey());
});

it('folds a moved fulfillment thread into the one the other customer already holds', function (): void {
    $seller = $this->seller();
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $anonymousFulfillment = $this->shippedFulfillmentFor($seller, $anonymous);
    $held = Conversation::openFor(
        ConversationSubject::fulfillment($seller->id, $verified->id, $anonymousFulfillment->id),
        $this->moment('2026-08-01 09:00:00'),
    );
    $moved = Conversation::openFor(
        ConversationSubject::fulfillment($seller->id, $anonymous->id, $anonymousFulfillment->id),
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

it('leaves the surviving fulfillment thread when neither side carries a message', function (): void {
    $seller = $this->seller();
    $anonymous = Customer::factory()->anonymous()->create();
    $verified = Customer::factory()->create();
    $fulfillment = $this->shippedFulfillmentFor($seller, $anonymous);
    $held = Conversation::openFor(
        ConversationSubject::fulfillment($seller->id, $verified->id, $fulfillment->id),
        $this->moment('2026-08-01 09:00:00'),
    );
    Conversation::openFor(
        ConversationSubject::fulfillment($seller->id, $anonymous->id, $fulfillment->id),
        $this->moment('2026-08-02 09:00:00'),
    );

    Conversation::moveCustomer($anonymous, $verified);

    expect(Conversation::count())->toBe(1)
        ->and($held->fresh()?->last_message_at?->format('Y-m-d H:i:s'))->toBe('2026-08-01 09:00:00');
});
