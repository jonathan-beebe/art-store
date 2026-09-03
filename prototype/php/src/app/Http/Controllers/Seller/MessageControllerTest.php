<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\PostMessage;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\MessageBody;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\Message;
use App\Support\ActorDisplay;
use Illuminate\Support\Facades\Config;

/**
 * The query string a row's own `href` carries, decoded into an array —
 * `type[]`/`status[]` come back as ordinary arrays rather than a raw
 * `type%5B%5D=...` string to match against.
 *
 * @return array<int|string, mixed>
 */
function sellerRowQuery(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

    return $params;
}

it('lists the sellers threads newest first with who, what, and unread count', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);
    $older = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listing->id,
        'last_message_at' => $this->moment('2026-08-20 09:00:00'),
    ]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $older->id, 'body' => 'Is this framed?']);

    $newerCustomer = $this->verifiedCustomer();
    $newer = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $newerCustomer->id,
        'listing_id' => $listing->id,
        'last_message_at' => $this->moment('2026-08-21 09:00:00'),
    ]);
    Message::factory()->from($newerCustomer)->unread()->create(['conversation_id' => $newer->id, 'body' => 'Do you ship to France?']);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertSeeInOrder(['Do you ship to France?', 'Is this framed?']);
    $response->assertSee('Harbour at Dusk');
    $response->assertSee('1 unread');
});

it('lists unread threads before read ones regardless of last_message_at', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $readNewer = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Read but newer',
        'last_message_at' => $this->moment('2026-08-21 09:00:00'),
    ]);
    Message::factory()->from($seller)->read()->create(['conversation_id' => $readNewer->id]);
    $unreadOlder = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Unread but older',
        'last_message_at' => $this->moment('2026-08-20 09:00:00'),
    ]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $unreadOlder->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertSeeInOrder(['Unread but older', 'Read but newer']);
});

it('lists an unread resolved thread under the default Open-only view, and hides a read one', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $unreadResolved = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Resolved but replied to',
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $unreadResolved->id]);
    $readResolved = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Resolved and read',
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);
    Message::factory()->from($customer)->read()->create(['conversation_id' => $readResolved->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertSee('Resolved but replied to');
    $response->assertDontSee('Resolved and read');
});

it('keeps another sellers threads off the inbox', function (): void {
    $listing = $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);
    Conversation::factory()->listingQuestion()->create(['seller_id' => $listing->seller_id, 'listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertDontSee('Not Mine');
});

it('names an order thread and a support thread on the inbox', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    Conversation::factory()->fulfillment()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'fulfillment_id' => $fulfillment->id,
    ]);
    $admin = $this->admin();
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'admin_id' => $admin->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages');

    $response->assertOk();
    $response->assertSee("Order {$fulfillment->order_id}");
    // A support thread names the desk on the seller's inbox, whether or
    // not an admin has answered yet.
    $response->assertSee(ActorDisplay::SUPPORT_DESK);
});

it('renders the inbox on a fixed number of queries however many threads the seller holds', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    foreach (range(1, 5) as $ignored) {
        $customer = $this->verifiedCustomer();
        $conversation = Conversation::factory()->listingQuestion()->create([
            'seller_id' => $seller->id,
            'customer_id' => $customer->id,
            'listing_id' => $listing->id,
        ]);
        Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);
    }

    $response = $this->actingAs($seller, 'seller')
        // +1 for the page-view roll-up's upsert, which runs after every
        // countable response (RollUpPageViews); +2 for the seller layout's
        // awaiting-shipment count and unread-notifications check; +1 for
        // the list pane's window total (`ListPaneWindow`, DSGN-006
        // follow-up) — a `count()` alongside the capped fetch, so the pane
        // and its footer can say how many conversations exist beyond the
        // window.
        ->expectsDatabaseQueryCount(10)
        ->get('/seller/messages');

    $response->assertOk();
});

it('shows every message in order and marks the thread read', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $this->listing($seller)->id,
    ]);
    $first = Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id, 'body' => 'Is this framed?']);
    $second = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Not yet.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSeeInOrder(['Is this framed?', 'Not yet.']);
    expect($first->fresh()?->read_at)->not->toBeNull()
        ->and($second->fresh()?->read_at)->toBeNull();
    // Below `sm`, an own-message panel widens from ~78% to ~90% so a phone
    // reads it comfortably.
    $response->assertSee('max-w-[90%] items-start gap-3 sm:max-w-[78%]', escape: false);
    // Server-rendered "Ctrl" — composer.js swaps it to "⌘" client-side on a
    // Mac, so the un-scripted render always says Ctrl.
    $response->assertSee('data-composer-mod', escape: false);
    $response->assertDontSee('&#8984;', escape: false);
});

it('answers not found for a thread the seller is not in', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertNotFound();
});

it('answers not found for a thread id that matches nothing', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/messages/999999');

    $response->assertNotFound();
});

it('carries the current domain, type, and status from an inbox row into the shows own pane', function (): void {
    $seller = $this->seller();
    $resolved = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'title' => 'Resolved question',
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $index = $this->actingAs($seller, 'seller')->get('/seller/messages?status[]=open&status[]=resolved');
    $index->assertOk();
    preg_match('#href="([^"]*'.preg_quote($resolved->id, '#').'[^"]*)"#', (string) $index->getContent(), $matches);
    $rowHref = html_entity_decode($matches[1] ?? '');
    expect(sellerRowQuery($rowHref)['status'] ?? null)->toBe(['open', 'resolved']);

    $show = $this->actingAs($seller, 'seller')->get($rowHref);

    $show->assertOk();
    // Rendered once in the transcript header and once in the pane's own
    // row beside it.
    expect(substr_count((string) $show->getContent(), 'Resolved question'))->toBeGreaterThanOrEqual(2);
});

it('defaults the show routes pane to every status, so a resolved thread lands in its own pane', function (): void {
    $seller = $this->seller();
    $resolved = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'title' => 'Resolved question',
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    // No query string at all: the index route's own default (status=open
    // only) would otherwise leave a resolved thread out of its pane.
    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$resolved->id}");

    $response->assertOk();
    expect(substr_count((string) $response->getContent(), 'Resolved question'))->toBeGreaterThanOrEqual(2);
});

it('prepends the selected thread to its pane when an explicit domain excludes it', function (): void {
    $seller = $this->seller();
    // `support` scopes to the seller's own desk thread, which a listing
    // question never matches.
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'title' => 'A framing question']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}?domain=support");

    $response->assertOk();
    $response->assertSee('A framing question');
});

it('appends a reply and returns to the thread with it visible', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => 'It ships within 3 days.']);

    $response->assertRedirect(route('seller.messages.show', [
        'conversation' => $conversation,
        'domain' => 'all',
        'type' => ['questions', 'orders', 'support'],
        'status' => ['open', 'resolved'],
    ]));
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'It ships within 3 days.')->exists())->toBeTrue();
    $this->actingAs($seller, 'seller')
        ->get(route('seller.messages.show', $conversation))
        ->assertSee('It ships within 3 days.');
});

it('carries the panes domain, type, and status onward through a replys redirect', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}?domain=buyers&type[]=questions&status[]=open", ['body' => 'It ships within 3 days.']);

    $response->assertRedirect(route('seller.messages.show', [
        'conversation' => $conversation,
        'domain' => 'buyers',
        'type' => ['questions'],
        'status' => ['open'],
    ]));
});

it('leaves the thread unread when the reply is refused', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $this->listing($seller)->id,
    ]);
    $question = Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
    expect($question->fresh()?->read_at)->toBeNull();
});

it('answers not found replying to a thread the seller is not in', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => 'Sneaking in.']);

    $response->assertNotFound();
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Sneaking in.')->exists())->toBeFalse();
});

it('offers publish as faq prefilled from the question and the sellers latest answer, only for a listing thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listing->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $conversation->id, 'body' => 'Is this framed?']);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Yes, framed in black wood.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertSee('Publish as FAQ');
    $response->assertSee('value="Is this framed?"', escape: false);
    $response->assertSee('Yes, framed in black wood.');
    // The disclosure carries the thread it sits on, so publishing returns
    // to it (docs/messaging.md § "Open and resolved": publishing resolves
    // the thread) rather than landing on the listing's own FAQ page.
    $response->assertSee('name="conversation_id" value="'.$conversation->id.'"', escape: false);
    // The disclosure's hidden fields carry the pane's own selection onward.
    $response->assertSee('name="domain" value="all"', escape: false);
    $response->assertSee('name="type[]" value="questions"', escape: false);
    $response->assertSee('name="status[]" value="open"', escape: false);
});

it('offers no publish as faq section for a support thread', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertDontSee('Publish as FAQ');
});

it('moves the thread to the top of the inbox after a reply', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $this->listing($seller)->id,
        'last_message_at' => $this->moment('2026-08-01 09:00:00'),
    ]);
    app(PostMessage::class)($conversation, $customer, MessageBody::of('Is this framed?'), $this->moment('2026-08-01 09:00:00'));

    $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", ['body' => 'Not yet framed.']);

    expect($conversation->fresh()?->last_message_at?->greaterThan($this->moment('2026-08-01 09:00:00')))->toBeTrue();
});

it('narrows the inbox to a domain', function (): void {
    $seller = $this->seller();
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'title' => 'A question about this piece']);
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'title' => 'Payout timing']);

    $buyers = $this->actingAs($seller, 'seller')->get('/seller/messages?domain=buyers');
    $buyers->assertSee('A question about this piece');
    $buyers->assertDontSee('Payout timing');

    $support = $this->actingAs($seller, 'seller')->get('/seller/messages?domain=support');
    $support->assertSee('Payout timing');
    $support->assertDontSee('A question about this piece');
});

it('narrows the inbox to listing questions when type[]=questions', function (): void {
    $seller = $this->seller();
    $question = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'title' => 'A question about this piece']);
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    Conversation::factory()->fulfillment()->create(['seller_id' => $seller->id, 'fulfillment_id' => $fulfillment->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages?type[]=questions');

    $response->assertOk();
    $response->assertSee('A question about this piece');
    $response->assertDontSee("Order {$fulfillment->order_id}");
});

it('ORs multiple types together, rather than requiring every one', function (): void {
    $seller = $this->seller();
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'title' => 'A question about this piece']);
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    Conversation::factory()->fulfillment()->create(['seller_id' => $seller->id, 'fulfillment_id' => $fulfillment->id]);
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'title' => 'Payout timing']);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages?type[]=questions&type[]=orders');

    $response->assertOk();
    $response->assertSee('A question about this piece');
    $response->assertSee("Order {$fulfillment->order_id}");
    $response->assertDontSee('Payout timing');
});

it('ORs multiple statuses together, rather than requiring every one', function (): void {
    $seller = $this->seller();
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'title' => 'Open question']);
    Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'title' => 'Resolved question',
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $default = $this->actingAs($seller, 'seller')->get('/seller/messages');
    $default->assertSee('Open question');
    $default->assertDontSee('Resolved question');

    $resolvedOnly = $this->actingAs($seller, 'seller')->get('/seller/messages?status[]=resolved');
    $resolvedOnly->assertSee('Resolved question');
    $resolvedOnly->assertDontSee('Open question');

    $both = $this->actingAs($seller, 'seller')->get('/seller/messages?status[]=open&status[]=resolved');
    $both->assertSee('Open question');
    $both->assertSee('Resolved question');
});

it('narrows the inbox to support threads when domain=support', function (): void {
    $seller = $this->seller();
    Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'title' => 'A question about this piece']);
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'title' => 'Payout timing']);

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages?domain=support');

    $response->assertOk();
    $response->assertSee('Payout timing');
    $response->assertDontSee('A question about this piece');
});

it('names an empty domain in its own words, with a way past a narrowing status', function (): void {
    $seller = $this->seller();
    Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $support = $this->actingAs($seller, 'seller')->get('/seller/messages?domain=support');
    $support->assertSee('No support conversations.');

    // The default status (Open only) hides the seller's only (resolved)
    // thread — the empty state names that, with a link past it.
    $open = $this->actingAs($seller, 'seller')->get('/seller/messages');
    $open->assertSee('No open conversations.');
    $open->assertSee(route('seller.messages.index', [
        'domain' => 'all',
        'type' => ['questions', 'orders', 'support'],
        'status' => ['open', 'resolved'],
    ]));
});

it('names an empty inbox with nothing narrowing it, and offers no way past it', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/messages?status[]=open&status[]=resolved');

    $response->assertOk();
    $response->assertSee('No conversations yet.');
    $response->assertDontSee('Show all');
});

it('shows the reply-to block when reply_to names a message of the thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    $question = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id, 'body' => 'Is this framed?']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}?reply_to={$question->id}");

    $response->assertOk();
    $response->assertSee('Replying to', escape: false);
    $response->assertSee('value="'.$question->id.'"', escape: false);
});

it('names the composers replying-to banner "You" when quoting the sellers own message, not the shop name', function (): void {
    $seller = $this->seller("Trelawney's Tower Studio");
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    $own = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'It ships flat.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}?reply_to={$own->id}");

    $response->assertOk();
    $response->assertSee('Replying to <strong class="font-semibold text-gray-900 dark:text-white">You</strong>', escape: false);
    $response->assertDontSee("Trelawney's Tower Studio", escape: false);
});

it('names an inline reply quote "You" when it quotes the viewers own message, not the shop name', function (): void {
    $seller = $this->seller("Trelawney's Tower Studio");
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    $own = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'It ships flat.']);
    Message::factory()->from($customer)->create([
        'conversation_id' => $conversation->id,
        'body' => 'Great, thanks!',
        'reply_to_message_id' => $own->id,
    ]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSee('<strong class="font-semibold text-gray-700 dark:text-gray-300">You</strong> &middot; It ships flat.', escape: false);
    $response->assertDontSee("Trelawney's Tower Studio", escape: false);
});

it('ignores a reply_to naming a message from another thread rather than 500ing', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    $otherThreadMessage = Message::factory()->create();

    $response = $this->actingAs($seller, 'seller')->get("/seller/messages/{$conversation->id}?reply_to={$otherThreadMessage->id}");

    $response->assertOk();
    $response->assertDontSee('Replying to', escape: false);
});

it('trips the message-post limit, handing the thread back with the reply still in the box', function (): void {
    Config::set('rate_limits.message_post', RateLimitValue::parse('1/1h', 'RATE_LIMIT_MESSAGE_POST'));
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", ['body' => 'First reply.']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", ['body' => 'Second reply.']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('First reply.');
    $response->assertSee('>Second reply.</textarea>', escape: false);
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Second reply.')->exists())->toBeFalse();
});

it('trips the message-post limit while replying, keeping the reply-to block from the flashed input', function (): void {
    Config::set('rate_limits.message_post', RateLimitValue::parse('1/1h', 'RATE_LIMIT_MESSAGE_POST'));
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    $quoted = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id, 'body' => 'Is this framed?']);
    $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", ['body' => 'First reply.']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/messages/{$conversation->id}", [
        'body' => 'Second reply.',
        'reply_to_message_id' => $quoted->id,
    ]);

    $response->assertStatus(429);
    $response->assertSee('Replying to', escape: false);
});
