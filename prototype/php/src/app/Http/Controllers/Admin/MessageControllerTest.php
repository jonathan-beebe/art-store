<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\PostMessage;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Seller;
use App\Support\ActorDisplay;
use App\Support\CustomerIdentity;
use App\Support\ListPaneWindow;
use Illuminate\Support\Facades\Config;
use Tests\CapturedStory;
use Tests\TestCase;

/**
 * The rail and drawer render the same nav-item markup, so every other
 * section's own count chip (Sellers, Customers, ...) can coincidentally
 * match a bare `>1</span>` too — this isolates one link's own `<a>...</a>`
 * block before checking it for a chip.
 */
$navLinkMarkup = function (string $html, string $href): string {
    preg_match('#<a\s+href="'.preg_quote($href, '#').'"[^>]*>[\s\S]*?</a>#', $html, $matches);

    return $matches[0] ?? '';
};

/**
 * The nav rail and drawer each carry the tool list as `<li>` elements
 * (DSGN-006's admin redesign) — Accounting carries the same chrome and no
 * `<li>` of its own (unlike the dashboard's directory links), so its count
 * isolates what a list pane itself renders.
 */
$chromeListItemCount = function (TestCase $test, Admin $admin): int {
    return substr_count((string) $test->actingAs($admin, 'admin')->get('/admin/accounting')->getContent(), '<li>');
};

it('lists the admins threads newest first with who, what, and unread count', function (): void {
    $admin = $this->admin();
    $olderSeller = $this->seller('Blue Kiln Studio');
    $older = Conversation::factory()->adminSeller()->create([
        'seller_id' => $olderSeller->id,
        'last_message_at' => $this->moment('2026-08-20 09:00:00'),
    ]);
    Message::factory()->from($olderSeller)->unread()->create(['conversation_id' => $older->id, 'body' => 'Can you review my listing?']);

    $newerCustomer = $this->verifiedCustomer();
    $newer = Conversation::factory()->adminCustomer()->create([
        'customer_id' => $newerCustomer->id,
        'last_message_at' => $this->moment('2026-08-21 09:00:00'),
    ]);
    Message::factory()->from($newerCustomer)->unread()->create(['conversation_id' => $newer->id, 'body' => 'My order never arrived.']);

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages');

    $response->assertOk();
    $response->assertSeeInOrder(['My order never arrived.', 'Can you review my listing?']);
    $response->assertSee('1 unread');
});

it('shows a support thread to every admin, the desk is collective', function (): void {
    $seller = $this->seller('Other Studio');
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'admin_id' => $this->admin()->id]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages?filter=all&status=all');

    $response->assertOk();
    $response->assertSee('Other Studio');
});

it('names a seller support thread and a customer support thread on the inbox', function (): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');
    $customer = Customer::factory()->create(['name' => 'Priya Shopper']);
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    Conversation::factory()->adminCustomer()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=all&status=all');

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Priya Shopper');
});

it('shows every message in order and marks the thread read', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    $first = Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id, 'body' => 'Can you review my listing?']);
    $second = Message::factory()->from($admin)->create(['conversation_id' => $conversation->id, 'body' => "I'll take a look."]);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSeeInOrder(['Can you review my listing?', "I'll take a look."]);
    expect($first->fresh()?->read_at)->not->toBeNull()
        ->and($second->fresh()?->read_at)->toBeNull();
    // Below `sm`, an own-message panel widens from ~78% to ~90% so a phone
    // reads it comfortably.
    $response->assertSee('max-w-[90%] gap-3 sm:max-w-[78%]', escape: false);
    // Server-rendered "Ctrl" — composer.js swaps it to "⌘" client-side on a
    // Mac, so the un-scripted render always says Ctrl, not the old bare
    // "⌘/Ctrl" combo.
    $response->assertSee('data-composer-mod', escape: false);
    $response->assertDontSee('&#8984;', escape: false);
});

it('defaults the show routes pane to the desks full list rather than the index routes work queue', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    // An oversight thread `needs-reply` (the index route's own default)
    // never matches, since it scopes to the two desk kinds.
    $conversation = Conversation::factory()->fulfillment()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSee($conversation->counterpartName(\App\Domain\Auth\ActorType::Admin));
});

it('prepends the selected thread to its pane when the given filter excludes it', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    // `sellers` scopes to admin<->seller desk threads, which a fulfillment
    // (seller <-> customer, oversight) thread never matches.
    $conversation = Conversation::factory()->fulfillment()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}?filter=sellers&status=open");

    $response->assertOk();
    $response->assertSee($conversation->counterpartName(\App\Domain\Auth\ActorType::Admin));
});

it('carries the current filter and status from an inbox row into the shows own pane', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $resolved = Conversation::factory()->fulfillment()->create([
        'seller_id' => $seller->id,
        'resolved_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $index = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=orders&status=all');
    $index->assertOk();
    preg_match('#href="([^"]*'.preg_quote($resolved->id, '#').'[^"]*)"#', (string) $index->getContent(), $matches);
    $rowHref = html_entity_decode($matches[1] ?? '');
    expect($rowHref)->toContain('filter=orders')->toContain('status=all');

    $show = $this->actingAs($admin, 'admin')->get($rowHref);

    $show->assertOk();
    $show->assertSee('aria-current="true"', escape: false);
});

it('shows the list panes empty-detail prompt on the index route', function (): void {
    Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller()->id]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages');

    $response->assertOk();
    $response->assertSee('Choose a conversation to read it.');
});

it('renders the list pane beside the detail pane, with a sibling conversation still on the list', function (): void {
    $admin = $this->admin();
    Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller('Rye Press')->id]);
    $viewed = Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller('Blue Kiln Studio')->id]);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
});

it('caps the list pane at the window size, however many conversations exist', function () use ($chromeListItemCount): void {
    $admin = $this->admin();
    for ($i = 0; $i < ListPaneWindow::SIZE + 5; $i++) {
        Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller("Seller {$i}")->id]);
    }

    $chromeListItems = $chromeListItemCount($this, $admin);
    $response = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=all&status=all');

    $response->assertOk();
    // The index route renders the same capped list twice — the `lg`-and-up
    // pane and the below-`lg` inbox `x-messaging.inbox` already carried —
    // so the window shows up twice over, on top of the nav rail/drawer's
    // own `<li>` chrome (subtracted out above).
    expect(substr_count((string) $response->getContent(), '<li>') - $chromeListItems)->toBe(ListPaneWindow::SIZE * 2);
});

it('keeps the viewed conversation on the list pane even when it sorts outside the window', function () use ($chromeListItemCount): void {
    $admin = $this->admin();
    $viewed = Conversation::factory()->adminSeller()->create([
        'seller_id' => $this->seller('Blue Kiln Studio')->id,
        'last_message_at' => now()->subDay(),
    ]);

    for ($i = 0; $i < ListPaneWindow::SIZE + 5; $i++) {
        Conversation::factory()->adminSeller()->create([
            'seller_id' => $this->seller("Seller {$i}")->id,
            'last_message_at' => now(),
        ]);
    }

    $chromeListItems = $chromeListItemCount($this, $admin);
    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    expect(substr_count((string) $response->getContent(), '<li>') - $chromeListItems)->toBe(ListPaneWindow::SIZE + 1);
});

it('says how many conversations the list pane is not showing, linked to the full list', function (): void {
    $admin = $this->admin();
    for ($i = 0; $i < ListPaneWindow::SIZE + 5; $i++) {
        Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller("Seller {$i}")->id]);
    }

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=all&status=all');

    $response->assertOk();
    $response->assertSee('Showing 50 of', false);
    $response->assertSee('href="'.htmlspecialchars(route('admin.messages.index', ['filter' => 'all', 'status' => 'all'])).'"', escape: false);
});

it('says nothing about a window that already holds every conversation', function (): void {
    $admin = $this->admin();
    Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller()->id]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages');

    $response->assertOk();
    $response->assertDontSee('Showing');
});

it('shows a thread to a second admin, even one that never sent to it', function (): void {
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller()->id]);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/messages/{$conversation->id}");

    $response->assertOk();
});

it('answers not found for a thread id that matches nothing', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages/999999');

    $response->assertNotFound();
});

it('appends a reply and returns to the thread with it visible', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => "I'll take a look."]);

    $response->assertRedirect(route('admin.messages.show', ['conversation' => $conversation, 'filter' => 'all', 'status' => 'all']));
    expect(Message::where('conversation_id', $conversation->id)->where('body', "I'll take a look.")->exists())->toBeTrue();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.messages.show', $conversation))
        ->assertSee("I'll take a look.");
});

it('carries the panes filter and status onward through a replys redirect', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}?filter=sellers&status=open", ['body' => "I'll take a look."]);

    $response->assertRedirect(route('admin.messages.show', ['conversation' => $conversation, 'filter' => 'sellers', 'status' => 'open']));
});

it('refuses a reply longer than the message limit', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller()->id]);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('leaves the thread unread when the reply is refused', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    $question = Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}", ['body' => '']);

    $response->assertSessionHasErrors('body');
    expect($question->fresh()?->read_at)->toBeNull();
});

it('refuses a reply into a seller/customer oversight thread, the two-sides invariant', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => 'Stepping in.']);

    $response->assertForbidden();
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Stepping in.')->exists())->toBeFalse();
});

it('moves the thread to the top of the inbox after a reply', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create([
        'seller_id' => $seller->id,
        'last_message_at' => $this->moment('2026-08-01 09:00:00'),
    ]);
    app(PostMessage::class)($conversation, $seller, MessageBody::of('Can you review my listing?'), $this->moment('2026-08-01 09:00:00'));

    $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}", ['body' => "I'll take a look."]);

    expect($conversation->fresh()?->last_message_at?->greaterThan($this->moment('2026-08-01 09:00:00')))->toBeTrue();
});

it('names an order thread and a support thread by their fulfillment counterpart', function (): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    Conversation::factory()->fulfillment()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'fulfillment_id' => $fulfillment->id,
    ]);

    // A fulfillment thread carries no admin participant column, so it never
    // reaches the admin's badge inbox — this pins that `withParticipant`
    // excludes it rather than showing every thread on the platform.
    $response = $this->actingAs($admin, 'admin')->get('/admin/messages');

    $response->assertOk();
    $response->assertDontSee("Order {$fulfillment->order_id}");
});

it('carries a sellers support request to the admin and the answer back', function () use ($navLinkMarkup): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');

    $this->actingAs($seller, 'seller')->post('/seller/support', [
        'title' => 'Payout timing',
        'body' => 'My payout is late.',
    ])->assertRedirect();
    $conversation = Conversation::sole();

    $inbox = $this->actingAs($admin, 'admin')->get('/admin/messages');
    $inbox->assertSee('Blue Kiln Studio');
    $inbox->assertSee('My payout is late.');
    $inbox->assertSee('1 unread');
    expect($navLinkMarkup((string) $inbox->getContent(), route('admin.messages.index')))->toContain('>1</span>');

    $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => 'Paid this morning.'])
        ->assertRedirect(route('admin.messages.show', ['conversation' => $conversation, 'filter' => 'all', 'status' => 'all']));

    $this->actingAs($seller, 'seller')->get('/seller/messages')->assertSee('>1</span>', escape: false);
    $this->actingAs($seller, 'seller')
        ->get("/seller/messages/{$conversation->id}")
        ->assertSee('Paid this morning.');
});

it('carries a customers support request to the admin and the answer back', function (): void {
    $admin = $this->admin();
    $customer = Customer::factory()->create(['name' => 'Priya Shopper']);
    $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id);
    $this->actingAs($customer, 'customer');

    $this->post('/support', ['subject' => 'Order never arrived', 'body' => 'My order never arrived.'])->assertRedirect();
    $conversation = Conversation::sole();

    $inbox = $this->actingAs($admin, 'admin')->get('/admin/messages');
    $inbox->assertSee('Priya Shopper');
    $inbox->assertSee('My order never arrived.');
    $inbox->assertSee('1 unread');

    $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => 'It ships tomorrow.']);

    $this->get('/messages')->assertSee('Messages (1)', escape: false);
    $this->get("/messages/{$conversation->id}")->assertSee('It ships tomorrow.');
});

it('lets the admin answer a blocked customer', function (): void {
    $admin = $this->admin();
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);
    $conversation = Conversation::factory()->adminCustomer()->create(['customer_id' => $customer->id]);

    $show = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}");
    $show->assertSee('name="body"', escape: false);

    $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => 'The block stands until we hear back.'])
        ->assertRedirect(route('admin.messages.show', ['conversation' => $conversation, 'filter' => 'all', 'status' => 'all']));
    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(1);
});

it('renders the inbox on a fixed number of queries however many threads the admin holds', function (): void {
    $admin = $this->admin();
    foreach (range(1, 5) as $ignored) {
        $seller = $this->seller();
        $sellerThread = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
        Message::factory()->from($seller)->unread()->create(['conversation_id' => $sellerThread->id]);

        $customer = $this->verifiedCustomer();
        $customerThread = Conversation::factory()->adminCustomer()->create(['customer_id' => $customer->id]);
        Message::factory()->from($customer)->unread()->create(['conversation_id' => $customerThread->id]);
    }

    $response = $this->actingAs($admin, 'admin')
        // +1 for the page-view roll-up's upsert, which runs after every
        // countable response (RollUpPageViews). +1 for the nav rail's
        // unread-message badge and its five per-section counts (DSGN-006,
        // AdminLayoutComposer) — one combined query of scalar subqueries,
        // run on every admin page regardless of which one is rendering.
        // +1 for the list pane's window total (`ListPaneWindow`, DSGN-006
        // follow-up) — a `count()` alongside the capped fetch, so the pane
        // and its footer can say how many conversations exist beyond the
        // window. No eager-load query for `admin`: none of these threads
        // has one yet, so the relation's key list is empty and Eloquent
        // skips the query rather than running an empty `whereIn`. +2 for
        // `latestMessage.sender`: a polymorphic eager load runs one query
        // per distinct sender type among the fetched rows, and this fixture
        // carries both a seller and a customer sender.
        ->expectsDatabaseQueryCount(9)
        ->get('/admin/messages');

    $response->assertOk();
});

it('trips the message-post limit on the admin site, handing the thread back with the reply still in the box', function (): void {
    Config::set('rate_limits.message_post', RateLimitValue::parse('1/1h', 'RATE_LIMIT_MESSAGE_POST'));
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create(['admin_id' => $admin->id]);
    $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}", ['body' => 'First reply.']);

    $log = CapturedStory::capture();
    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}", ['body' => 'Second reply.']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Art Store admin', escape: false);
    $response->assertSee('First reply.');
    $response->assertSee('>Second reply.</textarea>', escape: false);
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Second reply.')->exists())->toBeFalse();

    $line = $log->line('rate_limit.exceed', 'refused');

    /** @var array<string, mixed> $data */
    $data = $line['data'];

    expect($line['level'])->toBe('warn')
        ->and($data['limit'])->toBe('message_post')
        ->and($data['key'])->toBe($admin->id);
});

it('needs-reply lists only open desk threads waiting on the desk', function (): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');
    $waiting = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    Message::factory()->from($seller)->create(['conversation_id' => $waiting->id, 'body' => 'Still waiting.']);

    $answered = Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller('Rye Press')->id]);
    Message::factory()->create(['conversation_id' => $answered->id, 'body' => 'First ask.']);
    Message::factory()->from($admin)->create(['conversation_id' => $answered->id, 'body' => 'Already answered.']);

    $resolvedSeller = $this->seller('Third Studio');
    $resolved = Conversation::factory()->adminSeller()->create(['seller_id' => $resolvedSeller->id, 'resolved_at' => now()]);
    Message::factory()->from($resolvedSeller)->create(['conversation_id' => $resolved->id, 'body' => 'Resolved already.']);

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages');

    $response->assertOk();
    $response->assertSee('Still waiting.');
    $response->assertDontSee('Already answered.');
    $response->assertDontSee('Resolved already.');
});

it('filters the inbox by seller, customer, order, and question kind', function (): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');
    $customer = $this->verifiedCustomer();
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id, 'title' => ThreadTitle::of('Payout timing')->value]);
    Conversation::factory()->adminCustomer()->create(['customer_id' => $customer->id, 'title' => ThreadTitle::of('Where is my order?')->value]);
    $orderThread = Conversation::factory()->fulfillment()->create();
    $questionThread = Conversation::factory()->listingQuestion()->create();
    $fulfillment = Fulfillment::findOrFail($orderThread->fulfillment_id);
    $listing = Listing::findOrFail($questionThread->listing_id);

    $sellers = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=sellers&status=all');
    $sellers->assertSee('Payout timing');
    $sellers->assertDontSee('Where is my order?');

    $customers = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=customers&status=all');
    $customers->assertSee('Where is my order?');
    $customers->assertDontSee('Payout timing');

    $orders = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=orders&status=all');
    $orders->assertSee("Order {$fulfillment->order_id}");
    $orders->assertDontSee('Payout timing');

    $questions = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=questions&status=all');
    $questions->assertSee($listing->title);
    $questions->assertDontSee('Payout timing');
});

it('filter=all lists desk and oversight threads together', function (): void {
    $admin = $this->admin();
    Conversation::factory()->adminSeller()->create(['title' => ThreadTitle::of('Payout timing')->value]);
    $oversight = Conversation::factory()->fulfillment()->create();
    $fulfillment = Fulfillment::findOrFail($oversight->fulfillment_id);

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=all&status=all');

    $response->assertOk();
    $response->assertSee('Payout timing');
    $response->assertSee("Order {$fulfillment->order_id}");
});

it('status=resolved lists only resolved threads, status=all lists both', function (): void {
    $admin = $this->admin();
    Conversation::factory()->adminSeller()->create(['title' => ThreadTitle::of('Open one')->value]);
    Conversation::factory()->adminSeller()->create(['title' => ThreadTitle::of('Resolved one')->value, 'resolved_at' => now()]);

    $resolvedView = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=sellers&status=resolved');
    $resolvedView->assertSee('Resolved one');
    $resolvedView->assertDontSee('Open one');

    $allView = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=sellers&status=all');
    $allView->assertSee('Resolved one');
    $allView->assertSee('Open one');
});

it('names an empty filter in its own words, with a way past a narrowing status', function (): void {
    $admin = $this->admin();
    Conversation::factory()->adminSeller()->create(['resolved_at' => now()]);

    $customers = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=customers&status=all');
    $customers->assertSee('No customer conversations.');
    $customers->assertSee(route('admin.messages.index', ['filter' => 'customers', 'status' => 'all']));

    // The default needs-reply queue excludes the admin's only (resolved,
    // already-answered) thread — the empty state names that, with a link
    // past it.
    $needsReply = $this->actingAs($admin, 'admin')->get('/admin/messages');
    $needsReply->assertSee('No conversations need a reply.');
    $needsReply->assertSee(route('admin.messages.index', ['filter' => 'needs-reply', 'status' => 'all']));
});

it('names an empty inbox with nothing narrowing it, and offers no way past it', function (): void {
    $admin = $this->admin();

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages?filter=all&status=all');

    $response->assertOk();
    $response->assertSee('No conversations yet.');
    $response->assertDontSee('Show all');
});

it('shows an oversight thread read-only, with no composer and no mark-read', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->fulfillment()->create();
    $seller = Seller::findOrFail($conversation->seller_id);
    $customer = Customer::findOrFail($conversation->customer_id);
    $message = Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id, 'body' => 'Any update on tracking?']);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSee('Any update on tracking?');
    $response->assertDontSee('name="body"', escape: false);
    $response->assertSee('Message '.$seller->displayName());
    $response->assertSee('Message '.ActorDisplay::nameOf($customer));
    expect($message->fresh()?->read_at)->toBeNull();
});

it('renders both sides of an oversight thread on the left, neither is the desk', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->fulfillment()->create();
    $seller = Seller::findOrFail($conversation->seller_id);
    $customer = Customer::findOrFail($conversation->customer_id);
    Message::factory()->from($customer)->create(['conversation_id' => $conversation->id, 'body' => 'Any update?']);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Shipped yesterday.']);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertDontSee('rounded-tr-sm bg-stone-100', escape: false);
});

it('offers mark resolved on a desk thread and hides it on an oversight thread', function (): void {
    $admin = $this->admin();
    $desk = Conversation::factory()->adminSeller()->create();
    $oversight = Conversation::factory()->fulfillment()->create();

    $this->actingAs($admin, 'admin')->get("/admin/messages/{$desk->id}")->assertSee('Mark resolved');
    $this->actingAs($admin, 'admin')->get("/admin/messages/{$oversight->id}")->assertDontSee('Mark resolved');
});

it('shows the reply quote when reply_to names a message in this thread', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    $original = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Does this vase ship internationally?']);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}?reply_to={$original->id}");

    $response->assertOk();
    $response->assertSee('Replying to');
    $response->assertSee('Does this vase ship internationally?');
});

it('ignores a reply_to naming a message from another thread, never a 500', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create();
    $otherConversation = Conversation::factory()->adminSeller()->create();
    $foreignMessage = Message::factory()->create(['conversation_id' => $otherConversation->id, 'body' => 'Not this thread.']);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}?reply_to={$foreignMessage->id}");

    $response->assertOk();
    $response->assertDontSee('Replying to');
});

it('ignores a reply_to naming no message at all, never a 500', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create();

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}?reply_to=bogus-id");

    $response->assertOk();
});

it('posts a reply that quotes an earlier message in the same thread', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    $original = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id, 'body' => 'Does this vase ship internationally?']);

    $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}", [
        'body' => 'Yes, worldwide.',
        'reply_to_message_id' => $original->id,
    ]);

    $reply = Message::where('conversation_id', $conversation->id)->where('body', 'Yes, worldwide.')->sole();
    expect($reply->reply_to_message_id)->toBe($original->id);
});

it('ignores a reply_to_message_id naming a message from another thread on post, never a 500', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create();
    $otherConversation = Conversation::factory()->adminSeller()->create();
    $foreignMessage = Message::factory()->create(['conversation_id' => $otherConversation->id]);

    $response = $this->actingAs($admin, 'admin')->post("/admin/messages/{$conversation->id}", [
        'body' => 'Reply anyway.',
        'reply_to_message_id' => $foreignMessage->id,
    ]);

    $response->assertRedirect(route('admin.messages.show', ['conversation' => $conversation, 'filter' => 'all', 'status' => 'all']));
    $reply = Message::where('conversation_id', $conversation->id)->where('body', 'Reply anyway.')->sole();
    expect($reply->reply_to_message_id)->toBeNull();
});

it('carries the order as context on the oversight threads message buttons', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->fulfillment()->create();
    $fulfillment = Fulfillment::findOrFail($conversation->fulfillment_id);

    $response = $this->actingAs($admin, 'admin')->get("/admin/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSee(
        'href="'.htmlspecialchars(route('admin.sellers.show', $conversation->seller_id).'?fulfillment='.$fulfillment->id.'#message-seller-form').'"',
        escape: false,
    );
    $response->assertSee(
        'href="'.htmlspecialchars(route('admin.customers.show', $conversation->customer_id).'?order='.$fulfillment->order_id.'#message-customer-form').'"',
        escape: false,
    );
});
