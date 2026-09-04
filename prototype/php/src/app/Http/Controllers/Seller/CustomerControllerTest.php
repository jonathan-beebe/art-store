<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Message;
use App\Models\Seller;
use Tests\QueryString;

/**
 * The query a sortable column header links to, keyed by the column's own
 * label — read off the rendered `<th>` so a test names a column the way the
 * seller reads it.
 *
 * @return array<int|string, mixed>
 */
function customerHeaderQuery(string $html, string $label): array
{
    preg_match_all('#<th[^>]*aria-sort="([^"]*)"[^>]*>\s*<a href="([^"]*)"[^>]*>(.*?)</a>#s', $html, $headers, PREG_SET_ORDER);

    foreach ($headers as $header) {
        if (trim((string) preg_replace('/\s+/', ' ', strip_tags($header[3]))) === $label) {
            return QueryString::of(html_entity_decode($header[2]));
        }
    }

    return [];
}

/** The `aria-sort` the header for `$label` carries. */
function customerHeaderAriaSort(string $html, string $label): string
{
    preg_match_all('#<th[^>]*aria-sort="([^"]*)"[^>]*>\s*<a href="([^"]*)"[^>]*>(.*?)</a>#s', $html, $headers, PREG_SET_ORDER);

    foreach ($headers as $header) {
        if (trim((string) preg_replace('/\s+/', ' ', strip_tags($header[3]))) === $label) {
            return $header[1];
        }
    }

    return 'missing';
}

/**
 * The query a segment button links to, keyed by its own label.
 *
 * @return array<int|string, mixed>
 */
function customerSegmentQuery(string $html, string $label): array
{
    preg_match('#<div role="group" aria-label="Segment"[^>]*>(.*?)</div>#s', $html, $control);

    preg_match_all('#<a\s+href="([^"]*)"[^>]*>(.*?)</a>#s', $control[1] ?? '', $links, PREG_SET_ORDER);

    foreach ($links as $link) {
        if (trim((string) preg_replace('/\s+/', ' ', strip_tags($link[2]))) === $label) {
            return QueryString::of(html_entity_decode($link[1]));
        }
    }

    return [];
}

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

it('sits between Orders and Messages in the left rail of every seller screen', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertOk()
        ->assertSee(route('seller.customers.index'))
        ->assertSeeInOrder(['Orders', 'Customers', 'Messages']);
});

it('counts customers, repeat buyers, the average order, and open conversations above the table', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $once = Customer::factory()->create(['name' => 'Cho Chang']);
    $twice = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $this->paidFulfillmentFor($seller, $once, 10000)->order->update(['placed_at' => now()->subDay()]);
    $this->paidFulfillmentFor($seller, $twice, 10000)->order->update(['placed_at' => now()->subDays(2)]);
    $this->paidFulfillmentFor($seller, $twice, 40000)->order->update(['placed_at' => now()->subDay()]);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $twice->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers');

    $response->assertOk();

    expect($response->getContent())
        ->toMatch('/data-stat="customers">2</')
        ->toMatch('/data-stat="customers-new"[^>]*>\+2 new</')
        ->toMatch('/data-stat="repeat-buyers">1</')
        ->toMatch('/data-stat="repeat-share"[^>]*>50%</')
        ->toMatch('/data-stat="average-order">\$200\.00</')
        ->toMatch('/data-stat="open-conversations">1</')
        ->toMatch('/data-stat="unread-conversations"[^>]*>0 unread</');
});

it('says what makes someone a customer when the seller has none', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/customers');

    $response->assertOk()->assertSee('A paid order is what makes someone a customer.');
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

it('sorts a name alphabetically', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']), 5000);
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Cho Chang']), 9000);

    $this->actingAs($seller, 'seller')->get('/seller/customers?sort=name&dir=asc')
        ->assertSeeInOrder(['Cho Chang', 'Ginny Weasley']);
});

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

it('marks the sorted column with aria-sort, flips it, and opens every other one descending', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?sort=orders&dir=asc');
    $html = (string) $response->getContent();

    $response->assertOk();

    expect(customerHeaderAriaSort($html, 'Orders'))->toBe('ascending')
        ->and(customerHeaderAriaSort($html, 'Spent'))->toBe('none')
        ->and(customerHeaderQuery($html, 'Orders'))->toBe(['sort' => 'orders', 'dir' => 'desc'])
        ->and(customerHeaderQuery($html, 'Spent'))->toBe(['sort' => 'spent', 'dir' => 'desc']);
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

it('counts one parcel and still lists the settled one under Orders', function (string $settle, string $badge): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Neville Longbottom']);
    $this->paidFulfillmentFor($seller, $customer, 5000);
    $settled = $this->paidFulfillmentFor($seller, $customer, 9000);

    $settle === 'declined'
        ? app(DeclineFulfillment::class)($settled, 'The kiln cracked it.', $this->moment('2026-08-21 09:00:00'))
        : app(RefundFulfillment::class)($settled, $this->admin(), 'It arrived chipped.', $this->moment('2026-08-21 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}");

    $response->assertOk()
        ->assertSeeInOrder(['Orders', '1'])
        ->assertSee('$50.00')
        ->assertSee(route('seller.orders.show', $settled))
        ->assertSee($badge);
})->with([
    'declined' => ['declined', 'Declined'],
    'refunded' => ['refunded', 'Refunded'],
]);

it('answers 404 for a buyer whose only parcel this seller declined', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer);

    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked it.', $this->moment('2026-08-21 09:00:00'));

    $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}")->assertNotFound();
});

it('carries the segment through a sort link and the sort through a segment link', function (): void {
    $seller = Seller::factory()->create();

    $response = $this->actingAs($seller, 'seller')->get('/seller/customers?segment=repeat&sort=orders&dir=asc');
    $html = (string) $response->getContent();

    $response->assertOk();

    expect(customerHeaderQuery($html, 'Spent'))->toBe(['segment' => 'repeat', 'sort' => 'spent', 'dir' => 'desc'])
        ->and(customerSegmentQuery($html, 'New this period'))->toBe(['sort' => 'orders', 'dir' => 'asc', 'segment' => 'new']);
});

it('IMPRV-030 shows a placeholder image for an order row with no item to read one from', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Luna Lovegood']);
    $fulfillment = $this->paidFulfillmentFor($seller, $customer);
    // A valid, if unusual, state to render around: the order's line
    // deleted out from under it, the way `Fulfillment::itemLabel()`
    // already defends against ("no items") — no dangling foreign key
    // needed to reach it.
    $fulfillment->order->items()->delete();

    $response = $this->actingAs($seller, 'seller')->get("/seller/customers/{$customer->id}");

    $response->assertOk();
    $response->assertDontSee('src=""', escape: false);
});
