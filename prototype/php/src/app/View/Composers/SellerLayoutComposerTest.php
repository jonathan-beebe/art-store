<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Money\Money;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\Message;
use App\Notifications\ItemSold;

// The composer sets its data on the seller layout component's own view, not
// the page view the controller returns, so assertViewHas() can't see it —
// these read the rendered chip/dot markup instead, the way the tests they
// replace already did.

it('counts the messages across every thread the seller has not read', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listing->id,
    ]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertSee('>1</span>', escape: false);
});

it('drops the count once the thread is marked read', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);
    app(MarkConversationRead::class)($conversation, $seller, $this->moment('2026-08-20 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertDontSee('>1</span>', escape: false);
});

it('carries the count onto every seller page without the controller passing it', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $this->actingAs($seller, 'seller')->get('/seller/listings')->assertSee('>1</span>', escape: false);
    $this->actingAs($seller, 'seller')->get('/seller/orders')->assertSee('>1</span>', escape: false);
});

it('counts the fulfillments awaiting shipment', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertSee('>1</span>', escape: false);
});

it('drops a fulfillment from the count once it ships', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM1', $this->moment('2026-08-21 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertDontSee('>1</span>', escape: false);
});

it('flags unread notifications for the bell', function (): void {
    $seller = $this->seller();
    $seller->notify(new ItemSold('ord_00000000000000000000000041', Money::fromCents(9000)));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertSee('<span class="absolute top-2 right-2 block size-2 rounded-full bg-indigo-400 ring-2 ring-gray-900"></span>', escape: false);
});

it('drops the bell flag once every notification is read', function (): void {
    $seller = $this->seller();
    $seller->notify(new ItemSold('ord_00000000000000000000000041', Money::fromCents(9000)));
    $seller->unreadNotifications()->update(['read_at' => now()]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertDontSee('<span class="absolute top-2 right-2 block size-2 rounded-full bg-indigo-400 ring-2 ring-gray-900"></span>', escape: false);
});

it('renders a page with no seller signed in without the seller nav', function (): void {
    $response = $this->get('/seller/login');

    $response->assertOk();
    $response->assertDontSee('aria-label="Seller tools"', escape: false);
});
