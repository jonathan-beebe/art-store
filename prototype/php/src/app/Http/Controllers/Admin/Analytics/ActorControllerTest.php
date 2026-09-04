<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsEventName;
use App\Http\Middleware\LogRequestStory;
use Illuminate\Http\Request;

it('renders 200 with every actor in the range', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors');

    $response->assertOk();
    $response->assertSee('All actors');
    $response->assertSee($customer->id);
});

it('sorts by most active', function (): void {
    $listing = $this->listing($this->seller());
    $busy = $this->verifiedCustomer();
    $quiet = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $busy->id, $this->moment("2026-08-19 09:0{$i}:00")));
    }
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $quiet->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=active');

    $response->assertOk();
    $response->assertSeeInOrder([$busy->id, $quiet->id]);
});

it('sorts by most recent', function (): void {
    $listing = $this->listing($this->seller());
    $recent = $this->verifiedCustomer();
    $stale = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $stale->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $recent->id, $this->moment('2026-08-23 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=recent');

    $response->assertOk();
    $response->assertSeeInOrder([$recent->id, $stale->id]);
});

it('pages past the first page of actors', function (): void {
    $listing = $this->listing($this->seller());
    $analytics = app(Analytics::class);
    $ids = [];

    // One more actor than a page holds, each with a distinct last-seen
    // minute so "most recent" order is unambiguous — one event apiece
    // keeps the fixture cheap.
    $total = ActorController::PER_PAGE + 1;
    for ($minutesAgo = $total; $minutesAgo >= 1; $minutesAgo--) {
        $customer = $this->verifiedCustomer();
        $ids[] = $customer->id;
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')->modify('+'.($total - $minutesAgo).' minutes')));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $firstPage = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=recent');
    $secondPage = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=recent&page=2');

    $firstPage->assertOk()->assertSee($ids[$total - 1])->assertDontSee($ids[0]);
    $secondPage->assertOk()->assertSee($ids[0]);
});

it('clamps a page past the end to the last page', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?page=999');

    $response->assertOk();
    $response->assertSee($customer->id);
});

it('narrows by actor kind', function (): void {
    $listing = $this->listing($this->seller());
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $anonymous->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?actors=verified');

    $response->assertOk();
    $response->assertSee($verified->id)->assertDontSee($anonymous->id);
});

it('searches by actor id', function (): void {
    $listing = $this->listing($this->seller());
    $hermione = $this->verifiedCustomer();
    $ron = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $ron->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?q='.$hermione->id);

    $response->assertOk();
    $response->assertSee($hermione->id)->assertDontSee($ron->id);
});

it('searches by email', function (): void {
    $listing = $this->listing($this->seller());
    $hermione = $this->verifiedCustomer();
    $ron = $this->verifiedCustomer();
    $hermione->update(['email' => 'hermione@example.com']);
    $ron->update(['email' => 'ron@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $ron->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?q=hermione');

    $response->assertOk();
    $response->assertSee('hermione@example.com')->assertDontSee('ron@example.com');
});

it('searches by ip', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $request = Request::create('/', server: ['REMOTE_ADDR' => '185.220.101.42']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?q=185.220.101.42');

    $response->assertOk();
    $response->assertSee($customer->id);
});

it('answers 400 for an unrecognised sort', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=popular');

    $response->assertStatus(400);
});

it('renders 200 with the actor\'s facts, tiles, and feed', function (): void {
    $seller = $this->seller('Weasley Studio');
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $customer = $this->verifiedCustomer();
    $customer->update(['email' => 'hermione@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertSee('hermione@example.com');
    $response->assertSee($customer->id);
    $response->assertSee('The Burrow at Dusk');
    $response->assertSee($listing->id);
    $response->assertSee('Open customer');
    $response->assertSee('Open in logs');
    $response->assertSee('Block customer');
});

it('filters the actor feed by event name', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', ['customer' => $customer->id, 'event' => 'listing.favorite']));

    $response->assertOk();
    $response->assertSee('1 of 2 shown, newest first');
});

it('answers 400 for an unrecognised event filter on the actor page', function (): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', ['customer' => $customer->id, 'event' => 'nonsense']));

    $response->assertStatus(400);
});

it('answers not found for an unknown customer id on the actor page', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors/cus_01J5X3M9A2K8YB7Q4R6T1V0WZE');

    $response->assertNotFound();
});

it('flags an actor with 100+ events in one hour, showing the hourly strip and flag banner', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $request = Request::create('/', server: ['REMOTE_ADDR' => '185.220.101.42']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);

    foreach (range(1, 110) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 21:00:00')->modify("+{$i} seconds"), "flag-{$i}"));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertSee('By hour, Aug 19');
    $response->assertSee('185.220.101.42');
    $response->assertSee('no favorite or cart event in the range');
});

it('shows the merged-from fact on the actor page', function (): void {
    $anonymous = $this->anonymousCustomer();
    $verified = $this->verifiedCustomer();

    $this->travelTo($this->moment('2026-08-15 09:00:00'));
    \App\Models\CustomerMerge::factory()->create([
        'anonymous_customer_id' => $anonymous->id,
        'customer_id' => $verified->id,
    ]);

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $verified));

    $response->assertOk();
    $response->assertSee($anonymous->id);
    $response->assertSee('Aug 15, 2026');
});

it('names a deleted listing "listing no longer exists" on the actor page\'s feed', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();
    $listing->delete();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertSee('listing no longer exists');
});

it('names an order subject on the actor page\'s feed, linked to the order, with its listing titles', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Starry Night']);
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $listing);
    app(Analytics::class)->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', ['customer' => $customer, 'event' => 'order.place']));

    $response->assertOk();
    $response->assertSee("order {$order->id}");
    $response->assertSee('href="'.route('admin.orders.show', $order).'"', escape: false);
    $response->assertSee('Starry Night');
});

it('names a store subject on the actor page\'s feed, linked to the store', function (): void {
    $seller = $this->seller('Weasley Studio');
    $store = $this->storeFor($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $store->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertSee($store->name);
    $response->assertSee('href="'.route('admin.analytics.stores.show', $store).'"', escape: false);
});

it('names a store subject "store no longer exists" on the actor page\'s feed once the store is deleted', function (): void {
    $store = $this->storeFor($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $store->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();
    $store->delete();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertSee('store no longer exists');
});

it('names a cart subject on the actor page\'s feed, unlinked, with its listing titles', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Starry Night']);
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forCart(
        AnalyticsEventName::CheckoutOpen,
        $cart->id,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
        ['listing_ids' => [$listing->id]],
    ));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertSee("cart {$cart->id}");
    $response->assertSee('Starry Night');
});

it('renders the all-actors page on a fixed number of queries however many actors the range holds', function (): void {
    $listing = $this->listing($this->seller());
    $analytics = app(Analytics::class);

    foreach (range(1, 15) as $i) {
        $customer = $this->verifiedCustomer();
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(2, 'sqlite')
        ->expectsDatabaseQueryCount(4, 'analytics')
        ->get('/admin/analytics/actors');

    $response->assertOk();
});

it('renders one actor\'s page on a fixed number of queries however many events its feed holds', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 15) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')->modify("+{$i} minutes"), "e{$i}"));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(4, 'sqlite')
        ->expectsDatabaseQueryCount(12, 'analytics')
        ->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
});

it('shows the visits panel between the identity card and the tiles, with a channel and referrer', function (): void {
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit(
        $customer->id.'-one',
        $this->moment('2026-08-19 09:00:00'),
        '/art/starry-night',
        'newsletter.example.com',
        'newsletter',
        'email',
        'sept',
        null,
        null,
        $customer->id,
    ));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertSee('Visits');
    $response->assertSee('/art/starry-night');
    $response->assertSee('Email campaign: sept');
    $response->assertSee('newsletter.example.com');
    $response->assertSee('First channel');
});

it('escapes a utm value rather than rendering it raw', function (): void {
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit(
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
        '/',
        null,
        null,
        null,
        '<script>alert(1)</script>',
        null,
        null,
        $customer->id,
    ));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.actors.show', $customer));

    $response->assertOk();
    $response->assertDontSee('<script>alert(1)</script>', escape: false);
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
});
