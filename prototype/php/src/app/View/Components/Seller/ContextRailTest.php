<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Seller;
use App\Seller\ThreadContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\Blade;

function contextRailHtml(Seller $seller, Conversation $conversation): string
{
    return Blade::render(
        '<x-seller.context-rail :context="$context" />',
        ['context' => ThreadContext::forSeller($seller, $conversation, new DateTimeImmutable('2026-08-26 09:00:00'))],
    );
}

it('names the buyer and their numbers with this seller', function (): void {
    $seller = test()->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood', 'email' => 'luna@example.test']);
    test()->paidFulfillmentFor($seller, $customer, 68000)->order->update(['placed_at' => test()->moment('2026-06-01 09:00:00')]);
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => test()->listing($seller)->id]);

    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => test()->listing($seller, ['title' => 'Nine Owls', 'price_cents' => 68000, 'quantity' => 3])->id,
    ]);

    $html = contextRailHtml($seller, $conversation);

    expect($html)->toContain('Luna Lovegood')
        ->toContain('luna@example.test')
        ->toContain('>LL<')
        ->toContain('Orders')
        ->toMatch('/data-stat="orders">1</')
        ->toMatch('/data-stat="spent">\$680\.00</')
        ->toContain('Favorites')
        ->toContain('Since')
        ->toContain('Jun 1, 2026')
        ->toContain('View customer')
        ->toContain(route('seller.customers.show', $customer->id));
});

it('shows the piece a listing question is about', function (): void {
    $seller = test()->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood']);
    $listing = test()->listing($seller, ['title' => 'Nine Owls', 'price_cents' => 68000, 'quantity' => 3]);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listing->id,
    ]);

    $html = contextRailHtml($seller, $conversation);

    expect($html)->toContain('About this piece')
        ->toContain('Nine Owls')
        ->toContain('$680.00')
        ->toContain('3 in stock')
        ->toContain(route('seller.listings.show', $listing));

    expect($html)->not->toContain('About this order');
});

it('shows the parcel a fulfillment thread is about', function (): void {
    $seller = test()->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $fulfillment = test()->paidFulfillmentFor($seller, $customer, 12000);
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id))
        ->create();

    $html = contextRailHtml($seller, $conversation);

    expect($html)->toContain('About this order')
        ->toContain($fulfillment->order_id)
        ->toContain('$120.00')
        ->toContain('Awaiting shipment')
        ->toContain(route('seller.orders.show', $fulfillment));

    expect($html)->not->toContain('About this piece');
});

it('lists the buyer\'s other threads, each opening in the same pane', function (): void {
    $seller = test()->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $open = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'The one being read',
    ]);
    $other = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Does it ship boxed?',
        'last_message_at' => test()->moment('2026-08-25 09:00:00'),
    ]);

    $html = contextRailHtml($seller, $open);

    expect($html)->toContain('Other conversations')
        ->toContain('Does it ship boxed?')
        ->toContain(route('seller.messages.show', $other));

    expect($html)->not->toContain('The one being read');
});

it('shows the desk on a support thread, with no numbers and no other conversations', function (): void {
    $seller = test()->seller('The Burrow Craftworks');
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $html = contextRailHtml($seller, $conversation);

    expect($html)->toContain('Art Store Support')
        ->toContain('Support desk');

    expect($html)->not->toContain('View customer');
    expect($html)->not->toContain('Other conversations');
});

it('hands a visitor who has never bought a name and nothing else', function (): void {
    $seller = test()->seller('The Burrow Craftworks');
    $visitor = Customer::factory()->create(['name' => 'Draco Malfoy', 'email' => 'draco@example.test']);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $visitor->id,
        'listing_id' => test()->listing($seller)->id,
    ]);

    $html = contextRailHtml($seller, $conversation);

    expect($html)->toContain('Draco Malfoy')
        ->toContain('No email');

    expect($html)->not->toContain('draco@example.test');
    expect($html)->not->toContain('View customer');
});
