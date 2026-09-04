<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\PostMessage;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Listings\ListingStatus;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\Seller\ActivityTotal;
use App\Domain\Seller\AttentionGroup;
use App\Domain\Seller\AttentionRow;
use App\Models\Customer;
use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Seller;
use App\Models\Variant;
use App\Seller\ListingActivity;
use App\Seller\NavLink;
use App\Seller\OverviewListingRow;
use App\Seller\OverviewTile;
use DateTimeImmutable;
use RuntimeException;

function viewAt(string $listingId, string $when, int $times = 1): void
{
    $analytics = app(Analytics::class);

    for ($i = 0; $i < $times; $i++) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingId, null, new DateTimeImmutable($when)));
    }

    $analytics->flush();
}

/**
 * The tile a test is asking about, read out of the view's `tiles` array.
 *
 * @param  array<array-key, mixed>  $tiles
 */
function tileNamed(array $tiles, string $label): OverviewTile
{
    foreach ($tiles as $tile) {
        if ($tile instanceof OverviewTile && $tile->label === $label) {
            return $tile;
        }
    }

    throw new RuntimeException("no tile labelled {$label}");
}

/**
 * @param  array<array-key, mixed>  $groups
 */
function groupAt(array $groups, int $index): AttentionGroup
{
    $group = $groups[$index] ?? null;

    return $group instanceof AttentionGroup ? $group : throw new RuntimeException("no focus group at {$index}");
}

function molly(): Seller
{
    return test()->seller('The Burrow Craftworks');
}

/** The buyers and the pieces a test needs several of, in a fixed order. */
const BUYERS = ['Harry Potter', 'Ginny Weasley', 'Luna Lovegood', 'Neville Longbottom', 'Hermione Granger', 'Ron Weasley', 'Cho Chang'];

const PIECES = ['Nine Owls', 'Tea Bowl', 'Ochre Runner', 'Copper Cauldron', 'Garden Gnome', 'Tea Leaf Study', 'Orchard At First Light'];

it('renders the seller dashboard', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Needs your attention');
    $response->assertSee('Activity on your listings');
});

it('has a skip-to-content link targeting the main landmark', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertSee('<a href="#main-content"', escape: false);
    $response->assertSee('<main id="main-content"', escape: false);
});

it('links the built stylesheet', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertSee('/build/assets/', escape: false);
});

it('shows a flashed magic link in the debug alert', function (): void {
    $link = 'http://localhost:8000/auth/magic/abc123';

    $response = $this->actingAs($this->seller(), 'seller')
        ->withSession(['debug_magic_link' => $link])
        ->get('/seller');

    $response->assertSee($link, escape: false);
});

it('names the store and the window the page is read over', function (): void {
    $seller = molly();

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertSee('The Burrow Craftworks');
    $response->assertViewHas('range', fn (AnalyticsRange $range): bool => $range->days === 30);
});

it('offers the three ranges as links, the one in force marked current', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller?range=7');

    $response->assertViewHas('rangeLinks', function (array $links): bool {
        /** @var list<NavLink> $links */
        return count($links) === 3
            && $links[0]->label === '7 days'
            && $links[0]->active
            && ! $links[1]->active;
    });
});

it('counts every buyer on the customers tile, with the ones who arrived in the range', function (): void {
    $seller = molly();
    $this->deliveredFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter']), orderedAt: $this->moment('2026-08-20 10:00:00'));
    $this->deliveredFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']), orderedAt: $this->moment('2026-08-21 10:00:00'));

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('tiles', function (array $tiles): bool {
        $tile = tileNamed($tiles, 'Customers');

        return $tile->value === '2' && $tile->changeText === '+2 new';
    });
});

it('counts the orders placed in the range on the orders tile', function (): void {
    $seller = molly();
    $this->paidFulfillmentFor($seller, paidAt: $this->moment('2026-08-20 10:00:00'));

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('tiles', fn (array $tiles): bool => tileNamed($tiles, 'Orders')->value === '1');
});

it('leaves an order placed before the range out of the orders tile', function (): void {
    $seller = molly();
    $this->paidFulfillmentFor($seller, paidAt: $this->moment('2026-08-20 10:00:00'));

    $this->travelTo($this->moment('2026-12-01 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=7');

    $response->assertViewHas('tiles', fn (array $tiles): bool => tileNamed($tiles, 'Orders')->value === '0');
});

it('reads the net of the ranges live parcels on the earnings tile', function (): void {
    $seller = molly();
    $this->paidFulfillmentFor($seller, priceCents: 10000, paidAt: $this->moment('2026-08-20 10:00:00'));

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('tiles', fn (array $tiles): bool => tileNamed($tiles, 'Earnings')->value === '$90.00');
});

it('leaves a declined parcel out of the earnings and keeps it in the orders count', function (): void {
    $seller = molly();
    $parcel = $this->paidFulfillmentFor($seller, priceCents: 10000, paidAt: $this->moment('2026-08-20 10:00:00'));
    app(DeclineFulfillment::class)($parcel, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('tiles', function (array $tiles): bool {
        return tileNamed($tiles, 'Earnings')->value === '$0.00'
            && tileNamed($tiles, 'Orders')->value === '1';
    });
});

it('sends each tile to the tool that answers it', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller?range=7');

    $response->assertViewHas('tiles', function (array $tiles): bool {
        return str_contains(tileNamed($tiles, 'Customers')->href, '/seller/customers')
            && str_contains(tileNamed($tiles, 'Orders')->href, 'lane=ship')
            && str_contains(tileNamed($tiles, 'Earnings')->href, '/seller/earnings');
    });
});

it('IMPRV-037 carries no range to the customers tile link, customers being evergreen', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller?range=90');

    $response->assertViewHas('tiles', fn (array $tiles): bool => ! str_contains(tileNamed($tiles, 'Customers')->href, 'range'));
});

it('totals the ranges views, favorites, cart adds, and units sold', function (): void {
    $seller = molly();
    $parcel = $this->paidFulfillmentFor($seller, priceCents: 10000, paidAt: $this->moment('2026-08-20 10:00:00'));
    $listingId = $parcel->order->items()->sole()->listing_id;
    viewAt($listingId, '2026-08-25 10:00:00', times: 3);

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('activity', function (ListingActivity $activity): bool {
        $labels = array_map(fn (ActivityTotal $total): string => $total->label, $activity->totals);
        $counts = array_map(fn (ActivityTotal $total): int => $total->count, $activity->totals);

        return $labels === ['Views', 'Favorites', 'Cart adds', 'Sold'] && $counts[0] === 3 && $counts[3] === 1;
    });
});

it('lists the top five listings by views, most looked at first', function (): void {
    $seller = molly();
    $titles = ['Nine Owls', 'Tea Bowl', 'Ochre Runner', 'Copper Cauldron', 'Garden Gnome', 'Tea Leaf Study'];
    $listings = [];

    foreach ($titles as $index => $title) {
        $listing = $this->listing($seller, ['title' => $title, 'status' => ListingStatus::ForSale]);
        $listings[$title] = $listing;
        viewAt($listing->id, '2026-08-25 10:00:00', times: 10 - $index);
    }

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('activity', function (ListingActivity $activity): bool {
        $shown = array_map(fn (OverviewListingRow $row): string => $row->listing->title, $activity->rows);

        return $shown === ['Nine Owls', 'Tea Bowl', 'Ochre Runner', 'Copper Cauldron', 'Garden Gnome'];
    });
});

it('gives each listing row a daily strip and a link to the listing', function (): void {
    $seller = molly();
    $listing = $this->listing($seller, ['title' => 'Nine Owls']);
    viewAt($listing->id, '2026-08-25 10:00:00');

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('activity', function (ListingActivity $activity) use ($listing): bool {
        return $activity->stripDays === 30
            && count($activity->rows[0]->strip) === 30
            && $activity->rows[0]->href === route('seller.listings.show', ['listing' => $listing->id]);
    });
});

it('caps the strip at thirty days however long the range is', function (): void {
    $seller = molly();
    $this->listing($seller, ['title' => 'Nine Owls']);

    $response = $this->actingAs($seller, 'seller')->get('/seller?range=90');

    $response->assertViewHas('activity', fn (ListingActivity $activity): bool => $activity->stripDays === 30 && count($activity->rows[0]->strip) === 30);
});

it('says so in a sentence when a seller has no listings to draw anyone', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertSee('You have no listings yet, so there is nothing for buyers to look at.');
});

it('names the window the sold column counts over, the way the strip column does', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller?range=7');

    $response->assertSee('Sold, last 7 days');
    $response->assertSee('Views, last 7 days');
});

it('leads the focus row with the parcels waiting to ship, oldest first', function (): void {
    $seller = molly();
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter']), paidAt: $this->moment('2026-08-20 10:00:00'));
    $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']), paidAt: $this->moment('2026-08-25 10:00:00'));

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups): bool {
        /** @var list<AttentionGroup> $groups */
        $orders = groupAt($groups, 0);

        return $orders->title === '2 orders to ship'
            && $orders->actionHref === route('seller.orders.index', ['lane' => 'ship'])
            && count($orders->rows) === 2
            && str_contains($orders->rows[0]->meta, '15 days ago');
    });
});

it('marks a parcel that has waited past two days', function (): void {
    $seller = molly();
    $this->paidFulfillmentFor($seller, paidAt: $this->moment('2026-08-20 10:00:00'));

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', fn (array $groups): bool => groupAt($groups, 0)->rows[0]->urgent);
});

it('opens a to-ship row on the parcel itself', function (): void {
    $seller = molly();
    $parcel = $this->paidFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', fn (array $groups): bool => groupAt($groups, 0)->rows[0]->href === route('seller.orders.show', ['fulfillment' => $parcel->id, 'lane' => 'ship']));
});

it('lists the buyer threads holding a message the seller has not read', function (): void {
    $seller = molly();
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $listing = $this->listing($seller);

    $thread = app(OpenConversation::class)(
        ThreadOpening::listingQuestion($seller->id, $customer->id, $listing->id, ThreadTitle::of('Does it hold hot tea?')),
        $this->moment('2026-09-03 09:00:00'),
    );
    app(PostMessage::class)($thread, $customer, MessageBody::of('Does it hold hot tea without cracking?'), $this->moment('2026-09-03 09:00:00'));

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups) use ($thread): bool {
        /** @var list<AttentionGroup> $groups */
        $messages = groupAt($groups, 1);

        return $messages->title === '1 message waiting on you'
            && $messages->rows[0]->title === 'Harry Potter · Does it hold hot tea?'
            && $messages->rows[0]->supporting === 'Does it hold hot tea without cracking?'
            && $messages->rows[0]->href === route('seller.messages.show', ['conversation' => $thread->id]);
    });
});

it('names the payout day and what has released against what is still held', function (): void {
    $seller = molly();
    $this->deliveredFulfillmentFor($seller, priceCents: 10000);
    $this->paidFulfillmentFor($seller, priceCents: 20000);

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups): bool {
        /** @var list<AttentionGroup> $groups */
        $payout = groupAt($groups, 2);

        return str_starts_with($payout->title, 'Payout ')
            && $payout->rows[0]->title === '$90.00 released and ready'
            && $payout->rows[0]->supporting === '1 delivered order since the last payout'
            && $payout->rows[1]->title === '$180.00 still held'
            && $payout->rows[1]->supporting === '1 order waiting on delivery'
            && $payout->rows[1]->href === route('seller.earnings').'#held-heading';
    });
});

it('lists the drafts and sold-out pieces that cannot sell as they stand', function (): void {
    $seller = molly();
    $draft = $this->listing($seller, ['title' => 'Patchwork Shawl Runner', 'status' => ListingStatus::Draft]);
    $this->listing($seller, ['title' => 'Copper Cauldron Bowl', 'status' => ListingStatus::Sold]);
    $this->listing($seller, ['title' => 'Nine Owls', 'status' => ListingStatus::ForSale]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups) use ($draft): bool {
        /** @var list<AttentionGroup> $groups */
        $listings = groupAt($groups, 3);
        $titles = array_map(fn (AttentionRow $row): string => $row->title, $listings->rows);

        sort($titles);

        return $listings->title === '2 listings need work'
            && $titles === ['Copper Cauldron Bowl', 'Patchwork Shawl Runner']
            && in_array(route('seller.listings.show', ['listing' => $draft->id]), array_map(fn (AttentionRow $row): string => $row->href, $listings->rows), true);
    });
});

it('names a drafts own publish issue in its needs-work row', function (): void {
    $seller = molly();
    $draft = $this->listing($seller, ['title' => 'Patchwork Shawl Runner', 'status' => ListingStatus::Draft]);
    Modifier::factory()->count(ConfiguratorPublishValidation::MAX_MODIFIERS + 1)->create(['listing_id' => $draft->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups) use ($draft): bool {
        /** @var list<AttentionGroup> $groups */
        $rows = groupAt($groups, 3)->rows;
        $row = array_values(array_filter($rows, fn (AttentionRow $row): bool => $row->href === route('seller.listings.show', ['listing' => $draft->id])))[0] ?? null;

        return $row instanceof AttentionRow && $row->supporting === 'This listing asks more questions than the platform allows — remove one before it can go live.';
    });
});

it('counts a drafts remaining publish issues on its needs-work row', function (): void {
    $seller = molly();
    $draft = $this->listing($seller, ['title' => 'Patchwork Shawl Runner', 'status' => ListingStatus::Draft]);
    $axis = OptionAxis::factory()->create(['listing_id' => $draft->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    Variant::factory()->create(['listing_id' => $draft->id]);
    Modifier::factory()->count(ConfiguratorPublishValidation::MAX_MODIFIERS + 1)->create(['listing_id' => $draft->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups) use ($draft): bool {
        /** @var list<AttentionGroup> $groups */
        $rows = groupAt($groups, 3)->rows;
        $row = array_values(array_filter($rows, fn (AttentionRow $row): bool => $row->href === route('seller.listings.show', ['listing' => $draft->id])))[0] ?? null;

        return $row instanceof AttentionRow && str_ends_with($row->supporting, '+1 more');
    });
});

it('says each empty focus group in a sentence', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertSee('Nothing is waiting to ship.');
    $response->assertSee('Every buyer has heard back from you.');
    $response->assertSee('Every listing is published and in stock.');
});

it('counts the whole queue in a heading and links to the rest', function (): void {
    $seller = molly();

    foreach (BUYERS as $name) {
        $this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => $name]));
    }

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups): bool {
        /** @var list<AttentionGroup> $groups */
        $orders = groupAt($groups, 0);

        return $orders->title === '7 orders to ship' && count($orders->rows) === 5 && $orders->hidden() === 2;
    });
});

it('leaves another sellers work off the page', function (): void {
    $seller = molly();
    $other = $this->seller('Ollivanders');
    $this->paidFulfillmentFor($other);
    $this->listing($other, ['status' => ListingStatus::Draft]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', function (array $groups): bool {
        /** @var list<AttentionGroup> $groups */
        return groupAt($groups, 0)->title === 'No orders to ship'
            && groupAt($groups, 3)->title === 'No listings need work';
    });
    $response->assertViewHas('tiles', fn (array $tiles): bool => tileNamed($tiles, 'Orders')->value === '0');
});

it('renders on a fixed number of queries however many rows the seller holds', function (int $rows): void {
    $seller = molly();

    foreach (array_slice(PIECES, 0, $rows) as $index => $piece) {
        $listing = $this->listing($seller, ['status' => ListingStatus::ForSale, 'title' => $piece]);
        viewAt($listing->id, '2026-08-25 10:00:00', times: $index + 1);
        $this->listing($seller, ['status' => ListingStatus::Draft, 'title' => $piece.' (draft)']);

        $buyer = Customer::factory()->create(['name' => BUYERS[$index]]);
        $this->paidFulfillmentFor($seller, $buyer, priceCents: 20000);

        $thread = app(OpenConversation::class)(
            ThreadOpening::listingQuestion($seller->id, $buyer->id, $listing->id, ThreadTitle::of('Does '.$piece.' hold hot tea?')),
            $this->moment('2026-09-03 09:00:00'),
        );
        app(PostMessage::class)($thread, $buyer, MessageBody::of('Does it hold hot tea?'), $this->moment('2026-09-03 09:00:00'));
    }

    $this->deliveredFulfillmentFor($seller, priceCents: 10000);
    // The cart-add each checkout buffers is flushed here, so the setup's
    // own write stays out of the count the response below measures.
    app(Analytics::class)->flush();

    $this->travelTo($this->moment('2026-09-04 12:00:00'));

    // Six queues over two connections: the layout's two counts and the
    // page-view roll-up, the next payout, the buyers, the parcels placed
    // across both ranges and the refunds that netted against them, the
    // listings table, the units sold, and the four focus queues read down
    // and counted whole — plus DraftPublishIssues's ten grouped reads
    // across the needs-work panel's drafts, every one an unconditional
    // `whereIn` so the count holds at both row counts this test drives.
    $response = $this->actingAs($seller, 'seller')
        ->expectsDatabaseQueryCount(46)
        ->get('/seller');

    $response->assertOk();
})->with([
    'a handful of rows' => [2],
    'more rows than any panel shows' => [7],
]);

it('reads a listing row off the same figures the listings table shows', function (): void {
    $seller = molly();
    $listing = $this->listing($seller, ['title' => 'Nine Owls', 'price_cents' => 6000, 'quantity' => 3]);
    viewAt($listing->id, '2026-08-25 10:00:00', times: 4);

    $this->travelTo($this->moment('2026-09-04 12:00:00'));
    $response = $this->actingAs($seller, 'seller')->get('/seller?range=30');

    $response->assertViewHas('activity', function (ListingActivity $activity) use ($listing): bool {
        $row = $activity->rows[0];

        return $row->listing->id === $listing->id
            && $row->listing->views === 4
            && $row->listing->stockLabel() === '3 in stock';
    });
});

it('leaves an archived listing out of the work that needs doing', function (): void {
    $seller = molly();
    $this->listing($seller, ['status' => ListingStatus::Archived]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('attention', fn (array $groups): bool => groupAt($groups, 3)->title === 'No listings need work');
});
