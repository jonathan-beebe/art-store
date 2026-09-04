<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\Seller;

it('lists a buyer with their orders, spend, favorites, and conversations', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood', 'email' => 'luna@example.test']);
    $this->paidFulfillmentFor($seller, $customer, 68000);
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $this->listing($seller)->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers');

    $response->assertOk()
        ->assertSee('Luna Lovegood')
        ->assertSee('luna@example.test')
        ->assertSee('$680.00')
        ->assertSee(route('seller.customers.show', $customer->id));
});

it('leaves another seller\'s buyer off the list', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Lovegood Curiosities');
    $customer = Customer::factory()->create(['name' => 'Draco Malfoy']);
    $this->paidFulfillmentFor($other, $customer);

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers');

    $response->assertOk()->assertDontSee('Draco Malfoy');
});

it('names the seller\'s customers in the left rail', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers');

    $response->assertOk()->assertSee(route('seller.customers.index'));
});

it('narrows to repeat buyers', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $once = Customer::factory()->create(['name' => 'Cho Chang']);
    $twice = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $once);
    $this->paidFulfillmentFor($seller, $twice);
    $this->paidFulfillmentFor($seller, $twice);

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?segment=repeat');

    $response->assertOk()->assertSee('Ginny Weasley')->assertDontSee('Cho Chang');
});

it('narrows to buyers whose first order falls inside the range', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $old = Customer::factory()->create(['name' => 'Cho Chang']);
    $new = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $old)->order->update(['placed_at' => now()->subDays(120)]);
    $this->paidFulfillmentFor($seller, $new)->order->update(['placed_at' => now()->subDay()]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?segment=new&range=30');

    $response->assertOk()->assertSee('Ginny Weasley')->assertDontSee('Cho Chang');
});

it('sorts by every column in both directions', function (string $column, string $direction): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Cho Chang']), 5000);
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']), 9000);

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers?sort={$column}&dir={$direction}");

    $response->assertOk();
})->with(['name', 'orders', 'spent', 'favorites', 'last_order', 'conversations', 'since'])->with(['asc', 'desc']);

it('orders the rows by the sorted column and flips on the second click', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $small = Customer::factory()->create(['name' => 'Cho Chang']);
    $large = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $small, 5000);
    $this->paidFulfillmentFor($seller, $large, 9000);

    $descending = $this->actingAs($seller, 'seller')->get('/seller/customers?sort=spent&dir=desc');
    $ascending = $this->actingAs($seller, 'seller')->get('/seller/customers?sort=spent&dir=asc');

    $descending->assertSeeInOrder(['Ginny Weasley', 'Cho Chang']);
    $ascending->assertSeeInOrder(['Cho Chang', 'Ginny Weasley']);
});

it('marks the sorted column with aria-sort and links the rest to sort descending', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?sort=orders&dir=asc');

    $response->assertOk()
        ->assertSee('aria-sort="ascending"', escape: false)
        ->assertSee('sort=orders&amp;dir=desc', escape: false);
});

it('shows a buyer\'s identity, figures, orders, favorites, and conversations', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood', 'email' => 'luna@example.test']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer, 68000);
    $favorite = $this->listing($seller, ['title' => 'Nine Owls']);
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $favorite->id]);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Does it ship boxed?',
        'last_message_at' => $this->moment('2026-08-25 09:00:00'),
    ]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}");

    $response->assertOk()
        ->assertSee('Luna Lovegood')
        ->assertSee('luna@example.test')
        ->assertSee('$680.00')
        ->assertSee('Timeline')
        ->assertSee(route('seller.orders.show', $fulfillment))
        ->assertSee(route('seller.listings.show', $favorite))
        ->assertSee(route('seller.messages.show', $conversation))
        ->assertSee('Does it ship boxed?');
});

it('badges a buyer who has ordered twice as a repeat buyer', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $customer);
    $this->paidFulfillmentFor($seller, $customer);

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}");

    $response->assertOk()->assertSee('Repeat buyer');
});

it('narrows the timeline to one kind', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer);
    $listingId = $fulfillment->load('order.items')->order->items->sole()->listing_id;

    $analytics = new Analytics;
    $analytics->recordEvent(AnalyticsEvent::forListing(
        AnalyticsEventName::ListingView,
        (string) $listingId,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
    ));
    $analytics->flush();

    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Is it glazed?',
    ]);
    Message::factory()->from($customer)->create([
        'conversation_id' => $conversation->id,
        'body' => 'Is this piece glazed?',
        'sent_at' => $this->moment('2026-08-20 09:00:00'),
    ]);

    $whole = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}");
    $messagesOnly = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}?kind=messages");

    $whole->assertOk()->assertSee('Is this piece glazed?')->assertSee('placed order');
    $messagesOnly->assertOk()->assertSee('Is this piece glazed?')->assertDontSee('placed order');
});

it('answers 404 for a customer who has never bought from this seller', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $visitor = Customer::factory()->create(['name' => 'Draco Malfoy']);
    Favorite::factory()->create(['customer_id' => $visitor->id, 'listing_id' => $this->listing($seller)->id]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $visitor->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$visitor->id}");

    $response->assertNotFound();
});

it('answers 404 for another seller\'s buyer', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Lovegood Curiosities');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);
    $this->paidFulfillmentFor($other, $customer);

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}");

    $response->assertNotFound();
});

it('answers 404 for a buyer whose only parcel this seller declined', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer);

    app(\App\Actions\Fulfillment\DeclineFulfillment::class)($fulfillment, 'The kiln cracked it.', $this->moment('2026-08-21 09:00:00'));

    $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}")->assertNotFound();
});

it('sends a signed-out visitor to sign in', function (): void {
    $customer = Customer::factory()->create();

    $this->get('/seller/customers')->assertRedirect(route('auth.seller.login'));
    $this->get("/seller/customers/{$customer->id}")->assertRedirect(route('auth.seller.login'));
});

it('carries the segment through a sort link and the sort through a segment link', function (): void {
    $seller = Seller::factory()->create();

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?segment=repeat&sort=orders&dir=asc');

    $response->assertOk()
        ->assertSee('segment=repeat&amp;sort=spent&amp;dir=desc', escape: false)
        ->assertSee('sort=orders&amp;dir=asc&amp;segment=new', escape: false);
});
