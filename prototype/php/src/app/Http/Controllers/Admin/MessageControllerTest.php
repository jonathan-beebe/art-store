<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\PostMessage;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\MessageBody;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\Fulfillment;
use App\Models\Message;
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
function navLinkMarkup(string $html, string $href): string
{
    preg_match('#<a\s+href="'.preg_quote($href, '#').'"[^>]*>[\s\S]*?</a>#', $html, $matches);

    return $matches[0] ?? '';
}

/**
 * The nav rail and drawer each carry the tool list as `<li>` elements
 * (DSGN-006's admin redesign) — Accounting carries the same chrome and no
 * `<li>` of its own (unlike the dashboard's directory links), so its count
 * isolates what a list pane itself renders.
 */
function chromeListItemCount(TestCase $test, Admin $admin): int
{
    return substr_count((string) $test->actingAs($admin, 'admin')->get('/admin/accounting')->getContent(), '<li>');
}

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

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/messages');

    $response->assertOk();
    $response->assertSee('Other Studio');
});

it('names a seller support thread and a customer support thread on the inbox', function (): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');
    $customer = Customer::factory()->create(['name' => 'Priya Shopper']);
    Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    Conversation::factory()->adminCustomer()->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages');

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

it('caps the list pane at the window size, however many conversations exist', function (): void {
    $admin = $this->admin();
    for ($i = 0; $i < ListPaneWindow::SIZE + 5; $i++) {
        Conversation::factory()->adminSeller()->create(['seller_id' => $this->seller("Seller {$i}")->id]);
    }

    $chromeListItems = chromeListItemCount($this, $admin);
    $response = $this->actingAs($admin, 'admin')->get('/admin/messages');

    $response->assertOk();
    // The index route renders the same capped list twice — the `lg`-and-up
    // pane and the below-`lg` inbox `x-messaging.inbox` already carried —
    // so the window shows up twice over, on top of the nav rail/drawer's
    // own `<li>` chrome (subtracted out above).
    expect(substr_count((string) $response->getContent(), '<li>') - $chromeListItems)->toBe(ListPaneWindow::SIZE * 2);
});

it('keeps the viewed conversation on the list pane even when it sorts outside the window', function (): void {
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

    $chromeListItems = chromeListItemCount($this, $admin);
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

    $response = $this->actingAs($admin, 'admin')->get('/admin/messages');

    $response->assertOk();
    $response->assertSee('Showing 50 of', false);
    $response->assertSee('href="'.route('admin.messages.index').'"', escape: false);
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

    $response->assertRedirect(route('admin.messages.show', $conversation));
    expect(Message::where('conversation_id', $conversation->id)->where('body', "I'll take a look.")->exists())->toBeTrue();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.messages.show', $conversation))
        ->assertSee("I'll take a look.");
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

it('carries a sellers support request to the admin and the answer back', function (): void {
    $admin = $this->admin();
    $seller = $this->seller('Blue Kiln Studio');

    $this->actingAs($seller, 'seller')->get('/seller/support')->assertRedirect();
    $conversation = Conversation::sole();
    $this->actingAs($seller, 'seller')
        ->post("/seller/messages/{$conversation->id}", ['body' => 'My payout is late.']);

    $inbox = $this->actingAs($admin, 'admin')->get('/admin/messages');
    $inbox->assertSee('Blue Kiln Studio');
    $inbox->assertSee('My payout is late.');
    $inbox->assertSee('1 unread');
    expect(navLinkMarkup((string) $inbox->getContent(), route('admin.messages.index')))->toContain('>1</span>');

    $this->actingAs($admin, 'admin')
        ->post("/admin/messages/{$conversation->id}", ['body' => 'Paid this morning.'])
        ->assertRedirect(route('admin.messages.show', $conversation));

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

    $this->get('/support')->assertRedirect();
    $conversation = Conversation::sole();
    $this->post("/messages/{$conversation->id}", ['body' => 'My order never arrived.']);

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
        ->assertRedirect(route('admin.messages.show', $conversation));
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
        // skips the query rather than running an empty `whereIn`.
        ->expectsDatabaseQueryCount(7)
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
