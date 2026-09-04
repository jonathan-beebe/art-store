<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;

it('carries a buyer\'s identity and their numbers with this seller', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood', 'email' => 'luna@example.test']);
    $this->paidFulfillmentFor($seller, $customer, 68000);
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $this->listing($seller)->id]);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $this->listing($seller, ['title' => 'Nine Owls'])->id,
    ]);

    $context = ThreadContext::forSeller($seller, $conversation);

    expect($context->name)->toBe('Luna Lovegood')
        ->and($context->email)->toBe('luna@example.test')
        ->and($context->initials)->toBe('LL')
        ->and($context->isDesk)->toBeFalse()
        ->and($context->customer?->orders)->toBe(1)
        ->and($context->customer?->spentCents)->toBe(68000)
        ->and($context->customer?->favorites)->toBe(1)
        ->and($context->customerHref())->toBe(route('seller.customers.show', $customer->id))
        ->and($context->listing?->title)->toBe('Nine Owls');
});

it('names a visitor who has never bought and hands out no numbers and no email', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $visitor = Customer::factory()->create(['name' => 'Draco Malfoy', 'email' => 'draco@example.test']);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $visitor->id,
        'listing_id' => $this->listing($seller)->id,
    ]);

    $context = ThreadContext::forSeller($seller, $conversation);

    expect($context->name)->toBe('Draco Malfoy')
        ->and($context->customer)->toBeNull()
        ->and($context->email)->toBeNull()
        ->and($context->customerHref())->toBeNull();
});

it('carries the parcel a fulfillment thread is about', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer);
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id))
        ->create();

    $context = ThreadContext::forSeller($seller, $conversation);

    expect($context->order?->id)->toBe($fulfillment->id)
        ->and($context->listing)->toBeNull();
});

it('shows the desk instead of a customer on a support thread', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $context = ThreadContext::forSeller($seller, $conversation);

    expect($context->isDesk)->toBeTrue()
        ->and($context->name)->toBe('Art Store Support')
        ->and($context->customer)->toBeNull()
        ->and($context->others)->toBeEmpty();
});

it('lists the buyer\'s other threads with this seller, newest first', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Lovegood Curiosities');
    $customer = Customer::factory()->create(['name' => 'Ginny Weasley']);

    $open = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'last_message_at' => $this->moment('2026-08-20 09:00:00'),
    ]);
    $older = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'An older question',
        'last_message_at' => $this->moment('2026-08-10 09:00:00'),
    ]);
    $newer = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'A newer question',
        'last_message_at' => $this->moment('2026-08-25 09:00:00'),
    ]);
    Conversation::factory()->listingQuestion()->create([
        'seller_id' => $other->id,
        'customer_id' => $customer->id,
        'title' => 'Another seller\'s thread',
    ]);

    $context = ThreadContext::forSeller($seller, $open);

    expect($context->others->pluck('id')->all())->toBe([$newer->id, $older->id]);
});
