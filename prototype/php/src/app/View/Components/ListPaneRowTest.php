<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Domain\Listings\ListingStockLabel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Order;
use Tests\CommerceTestCase;

/**
 * Every `<a ...>...</a>` block whose opening tag carries `data-pane-cell` —
 * the seven panes that stamp the marker directly on the row (orders,
 * fulfillments, listings ×2, sellers, customers). The messaging inboxes
 * carry no `data-pane-cell` (their rows are `<li><a aria-current ...>`, not
 * a bare pane cell), so they are read out with {@see messagingRowBlocks()}
 * instead.
 *
 * @return list<array{attrs: string, inner: string}>
 */
function paneCellRowBlocks(string $html): array
{
    preg_match_all('/<a\s([^>]*)>(.*?)<\/a>/s', $html, $matches, PREG_SET_ORDER);

    $rows = array_map(fn (array $match): array => ['attrs' => $match[1], 'inner' => $match[2]], $matches);

    return array_values(array_filter($rows, fn (array $row): bool => str_contains($row['attrs'], 'data-pane-cell=')));
}

/**
 * A messaging inbox's own rows, bounded to the `<ul role="list"
 * class="divide-y ...">` block the inbox renders them in — the one `<ul>`
 * on an admin or seller page whose class marks it as a divided list rather
 * than the nav rail's own `<ul role="list">` (`-mx-2 flex-1 space-y-1` /
 * `space-y-1`, no `divide-y`), so this needs no `data-pane-cell` filter to
 * stay clear of the nav.
 *
 * @return list<array{attrs: string, inner: string}>
 */
function messagingRowBlocks(string $html): array
{
    if (! preg_match('/<ul role="list" class="divide-y[^"]*">(.*?)<\/ul>/s', $html, $bound)) {
        return [];
    }

    preg_match_all('/<a\s([^>]*)>(.*?)<\/a>/s', $bound[1], $matches, PREG_SET_ORDER);

    return array_map(fn (array $match): array => ['attrs' => $match[1], 'inner' => $match[2]], $matches);
}

/**
 * The `class="..."` value off one row block's opening-tag attributes.
 *
 * @param  array{attrs: string, inner: string}  $row
 */
function rowClass(array $row): string
{
    preg_match('/class="([^"]*)"/', $row['attrs'], $match);

    return $match[1] ?? '';
}

/**
 * The `href="..."` value off one row block's opening-tag attributes.
 *
 * @param  array{attrs: string, inner: string}  $row
 */
function rowHref(array $row): string
{
    preg_match('/href="([^"]*)"/', $row['attrs'], $match);

    return $match[1] ?? '';
}

/**
 * A row's class list with the one difference the two portals keep on
 * purpose — the stone/gray (admin) vs. indigo (seller) accent, in both its
 * plain-token and `shadow-[inset_2px_0_0_0_...]` arbitrary-value forms —
 * folded to one placeholder, and every class that only a *selected* row
 * carries (the fill, the inset-rail shadow, and the ring some idioms use
 * instead) stripped out. What is left is the row's shape: the one thing
 * every pane's row is meant to share.
 */
function normalizeRowClass(string $class): string
{
    $class = (string) preg_replace('/shadow-\[inset_2px_0_0_0_[^\]]+\]/', 'shadow-[inset_2px_0_0_0_ACCENT]', $class);
    $class = (string) preg_replace('/\b(?:stone|gray|indigo)-(\d+)\b/', 'ACCENT-$1', $class);

    $tokens = preg_split('/\s+/', trim($class));
    $tokens = $tokens === false ? [] : $tokens;

    $selectedOnly = [
        'bg-ACCENT-50',
        'dark:bg-ACCENT-800/60',
        'dark:bg-white/5',
        'shadow-[inset_2px_0_0_0_ACCENT]',
        'dark:shadow-[inset_2px_0_0_0_ACCENT]',
        'ring-2',
        'ring-inset',
        'ring-ACCENT-500',
    ];

    return implode(' ', array_values(array_diff($tokens, $selectedOnly)));
}

/**
 * Seeds at least two rows on each of the nine list panes a shared row
 * component would cover (admin: orders, fulfillments, listings, sellers,
 * customers, messages; seller: listings, orders, messages) and renders
 * every index page once. At least two rows per pane, not one, so a regex
 * asserting something of "every row" cannot pass by only ever seeing the
 * first. Hands back the admin order and the seller listing the "facts
 * survive" test reads its row back against, since both are seeded with
 * facts specific enough to assert on (a named customer, a priced and
 * stocked listing).
 *
 * @return array{pages: array<string, string>, order: Order, listing: Listing}
 */
function seedPanePages(CommerceTestCase $test): array
{
    $admin = $test->admin();
    $seller = $test->seller('Ollivanders');
    $otherSeller = $test->seller('Weasleys Wizard Wheezes');

    $hermione = Customer::factory()->create(['name' => 'Hermione Granger']);
    $luna = Customer::factory()->create(['name' => 'Luna Lovegood']);

    $wand = $test->listing($seller, ['title' => 'Phoenix Feather Wand', 'price_cents' => 4999, 'quantity' => 3]);
    $cloak = $test->listing($otherSeller, ['title' => 'Invisibility Cloak', 'price_cents' => 19999]);

    $order = $test->orderFor($hermione, $wand);
    $test->orderFor($luna, $cloak);

    // Both fulfillments sit under $seller (not split across the two
    // sellers) so the seller-scoped /seller/orders pane, not only the
    // unscoped /admin/fulfillments one, ends up with its own two rows.
    $test->paidFulfillmentFor($seller, $hermione, 4999);
    $test->paidFulfillmentFor($seller, $luna, 7500);

    // Both inboxes' rows carry an unread message so the pane renders its
    // unread dot, and distinct `last_message_at` values fix their order on
    // the unfiltered index page these tests render.
    $adminSellerThread = Conversation::factory()->adminSeller()->create([
        'seller_id' => $seller->id,
        'last_message_at' => $test->moment('2026-08-20 09:00:00'),
    ]);
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $adminSellerThread->id, 'body' => 'Can you review my listing?']);
    $adminCustomerThread = Conversation::factory()->adminCustomer()->create([
        'customer_id' => $luna->id,
        'last_message_at' => $test->moment('2026-08-21 09:00:00'),
    ]);
    Message::factory()->from($luna)->unread()->create(['conversation_id' => $adminCustomerThread->id, 'body' => 'My order never arrived.']);

    $questionOne = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $hermione->id,
        'listing_id' => $wand->id,
        'last_message_at' => $test->moment('2026-08-20 09:00:00'),
    ]);
    Message::factory()->from($hermione)->unread()->create(['conversation_id' => $questionOne->id, 'body' => 'Is this wand ready to ship?']);
    $questionTwo = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $luna->id,
        'listing_id' => $wand->id,
        'last_message_at' => $test->moment('2026-08-21 09:00:00'),
    ]);
    Message::factory()->from($luna)->unread()->create(['conversation_id' => $questionTwo->id, 'body' => 'Does it work for a left-hander?']);

    $get = fn (string $uri, string $guard) => (string) $test->actingAs($guard === 'admin' ? $admin : $seller, $guard)->get($uri)->getContent();

    return [
        'pages' => [
            'admin.orders' => $get('/admin/orders', 'admin'),
            'admin.fulfillments' => $get('/admin/fulfillments', 'admin'),
            'admin.listings' => $get('/admin/listings', 'admin'),
            'admin.sellers' => $get('/admin/sellers', 'admin'),
            'admin.customers' => $get('/admin/customers', 'admin'),
            'admin.messages' => $get('/admin/messages', 'admin'),
            'seller.listings' => $get('/seller/listings', 'seller'),
            'seller.orders' => $get('/seller/orders', 'seller'),
            'seller.messages' => $get('/seller/messages', 'seller'),
        ],
        'order' => $order,
        'listing' => $wand,
    ];
}

/**
 * Every row block, per pane — the seven `data-pane-cell` panes plus the two
 * messaging inboxes read out by their own bounded extraction. Kept keyed by
 * pane (rather than flattened) so a caller can assert "at least two rows"
 * pane by pane: a factory state two of the seeded fixtures touch
 * (`ConversationFactory`'s `listingQuestion()`/`adminSeller()`/
 * `adminCustomer()` states, and `MessageFactory`'s own default sender) each
 * also creates a throwaway seller, customer, or listing as a side effect of
 * building the row it needs, and `PlaceOrder` opens a fulfillment the
 * moment an order is placed — so several panes end up with more than two
 * rows. More rows only makes "every row" a stronger claim.
 *
 * @param  array<string, string>  $pages
 * @return array<string, list<array{attrs: string, inner: string}>>
 */
function rowsByPane(array $pages): array
{
    $rows = [];

    foreach ($pages as $pane => $html) {
        $rows[$pane] = str_ends_with($pane, '.messages') ? messagingRowBlocks($html) : paneCellRowBlocks($html);
    }

    return $rows;
}

/**
 * Fails loudly, pane by pane, if a fixture regression ever drops a pane
 * back to a single row — the count that would let a per-row regex pass
 * without ever proving "every row" holds across more than one.
 *
 * @param  array<string, list<array{attrs: string, inner: string}>>  $rowsByPane
 */
function assertEveryPaneHasAtLeastTwoRows(array $rowsByPane): void
{
    foreach ($rowsByPane as $pane => $rows) {
        expect(count($rows))->toBeGreaterThanOrEqual(2, "expected at least two rows on {$pane}, found ".count($rows));
    }
}

/**
 * @param  array<string, list<array{attrs: string, inner: string}>>  $rowsByPane
 * @return list<array{attrs: string, inner: string}>
 */
function flattenRows(array $rowsByPane): array
{
    return array_merge(...array_values($rowsByPane));
}

it('renders one row shape across all nine list panes, admin and seller accent aside', function (): void {
    $rowsByPane = rowsByPane(seedPanePages($this)['pages']);
    assertEveryPaneHasAtLeastTwoRows($rowsByPane);
    $rows = flattenRows($rowsByPane);

    $classes = array_map(fn (array $row): string => normalizeRowClass(rowClass($row)), $rows);

    // Fails today: `flex items-center gap-3 px-6 py-3` (orders,
    // fulfillments, listings, seller listings), the card-row's `flex
    // flex-col gap-1 p-4` idiom (sellers, customers, seller orders — which
    // itself differs from the card-row's own dark hover), and the
    // messaging inboxes' `block px-6 py-4` (the seller inbox carrying its
    // own focus-visible outline besides) are at least four distinct shapes,
    // not one.
    expect(array_values(array_unique($classes)))->toHaveCount(1);
});

it('gives every pane row a trailing chevron marked data-row-chevron', function (): void {
    $rowsByPane = rowsByPane(seedPanePages($this)['pages']);
    assertEveryPaneHasAtLeastTwoRows($rowsByPane);
    $rows = flattenRows($rowsByPane);

    // A `data-row-chevron` marker, not the chevron's own SVG path: the
    // stacked-list block's chevron is a solid 20-viewbox icon and the
    // codebase's own chevron elsewhere is Heroicons' outline glyph, two
    // different `d` strings that would make the test couple to whichever
    // one the implementer picks. The marker is the one part guaranteed
    // stable across that choice — and none of today's rows render either
    // icon, so this fails regardless.
    foreach ($rows as $row) {
        expect($row['inner'])->toContain('data-row-chevron');
    }
});

it('gives every pane row the stacked-list block\'s py-5 vertical rhythm', function (): void {
    $rowsByPane = rowsByPane(seedPanePages($this)['pages']);
    assertEveryPaneHasAtLeastTwoRows($rowsByPane);
    $rows = flattenRows($rowsByPane);

    foreach ($rows as $row) {
        $tokens = preg_split('/\s+/', trim(rowClass($row)));
        $tokens = $tokens === false ? [] : $tokens;

        // Fails today: orders/fulfillments/listings/seller-listings carry
        // `py-3`, the card-row idiom and seller orders carry `p-4`, and
        // both messaging inboxes carry `py-4` — none carry `py-5`, the
        // padding the stacked-list block pins as the row's own vertical rhythm.
        expect($tokens)->toContain('py-5');
    }
});

it('keeps the selected row marked and the whole row one link, on a show route in each portal', function (): void {
    $seeded = seedPanePages($this);
    $order = $seeded['order'];
    $listing = $seeded['listing'];

    $adminShow = (string) $this->actingAs($this->admin(), 'admin')->get(route('admin.orders.show', $order))->getContent();
    $sellerShow = (string) $this->actingAs($listing->seller, 'seller')->get(route('seller.listings.show', $listing))->getContent();

    $adminRow = array_values(array_filter(paneCellRowBlocks($adminShow), fn (array $row): bool => str_contains($row['attrs'], 'data-pane-cell="'.$order->id.'"')));
    $sellerRow = array_values(array_filter(paneCellRowBlocks($sellerShow), fn (array $row): bool => str_contains($row['attrs'], 'data-pane-cell="'.$listing->id.'"')));

    expect($adminRow)->toHaveCount(1);
    expect($sellerRow)->toHaveCount(1);

    expect($adminRow[0]['attrs'])->toContain('aria-current="true"');
    expect($sellerRow[0]['attrs'])->toContain('aria-current="page"');

    expect(rowHref($adminRow[0]))->toBe(route('admin.orders.show', $order));
    expect(rowHref($sellerRow[0]))->toBe(route('seller.listings.show', $listing));
});

it('keeps the facts an admin order row and a seller listing row show today', function (): void {
    $seeded = seedPanePages($this);
    $order = $seeded['order'];
    // Reloaded, not the seeded instance — placing the order above claimed
    // stock, so the listing's own `quantity` has moved since it was built.
    $listing = Listing::findOrFail($seeded['listing']->id);

    $orderRow = array_values(array_filter(
        paneCellRowBlocks($seeded['pages']['admin.orders']),
        fn (array $row): bool => str_contains($row['attrs'], 'data-pane-cell="'.$order->id.'"'),
    ))[0];

    $listingRow = array_values(array_filter(
        paneCellRowBlocks($seeded['pages']['seller.listings']),
        fn (array $row): bool => str_contains($row['attrs'], 'data-pane-cell="'.$listing->id.'"'),
    ))[0];

    // OrderControllerTest and CustomerControllerTest already pin the
    // customer name and status pill; this only adds the facts they don't:
    // the item count, the total, and the placed date on the row itself.
    expect($orderRow['inner'])
        ->toContain($order->customer->displayName())
        ->toContain('1 item')
        ->toContain($order->total()->format())
        ->toContain($order->placed_at->format('M j'));

    // Seller/ListingControllerTest pins the status badge; this adds the
    // title, price, and stock label the row's muted line carries.
    expect($listingRow['inner'])
        ->toContain($listing->title)
        ->toContain($listing->price()->format())
        ->toContain(ListingStockLabel::withInStock($listing->quantity));
});
