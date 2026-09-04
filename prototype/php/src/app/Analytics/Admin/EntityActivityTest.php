<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Middleware\LogRequestStory;
use App\Models\CustomerMerge;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\StoreProfile;
use Illuminate\Http\Request;

/**
 * @param  list<EntityFact>  $facts
 */
function entityFact(array $facts, string $label): EntityFact
{
    $found = collect($facts)->firstWhere('label', $label);
    assert($found instanceof EntityFact);

    return $found;
}

/**
 * @param  list<EventTile>  $tiles
 */
function entityTile(array $tiles, string $label): EventTile
{
    $found = collect($tiles)->firstWhere('label', $label);
    assert($found instanceof EventTile);

    return $found;
}

/**
 * Binds an in-flight request carrying the given ip, so the next recorded
 * event reads it back from {@see \App\Analytics\RequestFacts::current()}.
 */
function withIp(string $ip): void
{
    $request = Request::create('/', server: ['REMOTE_ADDR' => $ip]);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);
}

it('reads a listing\'s facts, tiles, and daily strip', function (): void {
    $seller = $this->seller('Weasley Studio');
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $anonymous->id, $this->moment('2026-08-20 09:00:00'), 'b'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $verified->id, $this->moment('2026-08-20 10:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $verified->id, $this->moment('2026-08-20 11:00:00')));
    $analytics->flush();

    Favorite::factory()->create(['listing_id' => $listing->id, 'customer_id' => $verified->id]);

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forListing($listing, $range, null);

    expect($view->kind)->toBe('listing')
        ->and($view->id)->toBe($listing->id)
        ->and($view->title)->toBe('The Burrow at Dusk')
        ->and($view->flagged)->toBeFalse()
        ->and($view->flagText)->toBe('');

    expect(entityFact($view->facts, 'Seller')->value)->toBe('Weasley Studio')
        ->and(entityFact($view->facts, 'Status')->value)->toBe($listing->status->label())
        ->and(entityFact($view->facts, 'Price')->value)->toBe($listing->price()->format());

    expect(entityTile($view->tiles, 'Views')->value)->toBe('2')
        ->and(entityTile($view->tiles, 'Favorites')->value)->toBe('1')
        ->and(entityTile($view->tiles, 'Favorites')->note)->toBe('1 standing today')
        ->and(entityTile($view->tiles, 'Cart adds')->value)->toBe('1')
        ->and(entityTile($view->tiles, 'Distinct actors')->value)->toBe('2')
        ->and(entityTile($view->tiles, 'Distinct actors')->note)->toBe('1 anonymous');

    expect($view->stripTitle)->toBe('By day')
        ->and($view->strip)->toHaveCount(7);
});

it('reads a store\'s facts, tiles, and daily strip', function (): void {
    $seller = $this->seller('Weasley Studio');
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id, 'name' => "Weasleys' Wizard Wheezes", 'slug' => 'weasleys-wizard-wheezes']);
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $profile->id, $verified->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $profile->id, $anonymous->id, $this->moment('2026-08-20 09:00:00'), 'b'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forStore($profile, $range, null);

    expect($view->kind)->toBe('store')
        ->and($view->id)->toBe($profile->id)
        ->and($view->title)->toBe("Weasleys' Wizard Wheezes")
        ->and($view->flagged)->toBeFalse()
        ->and($view->flagText)->toBe('')
        ->and($view->visits)->toBe([]);

    expect(entityFact($view->facts, 'Slug')->value)->toBe('weasleys-wizard-wheezes')
        ->and(entityFact($view->facts, 'Seller')->value)->toBe('Weasley Studio')
        ->and(entityFact($view->facts, 'Visibility')->value)->toBe($profile->visibility()->label());

    expect(entityTile($view->tiles, 'Views')->value)->toBe('2')
        ->and(entityTile($view->tiles, 'Distinct actors')->value)->toBe('2')
        ->and(entityTile($view->tiles, 'Distinct actors')->note)->toBe('1 anonymous');

    expect($view->stripTitle)->toBe('By day')
        ->and($view->strip)->toHaveCount(7);
});

it('reads a verified actor\'s identity, ips, and merged-from fact', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $anonymous = $this->anonymousCustomer();
    $verified = $this->verifiedCustomer();
    $verified->update(['email' => 'hermione@example.com']);
    $analytics = app(Analytics::class);

    $this->travelTo($this->moment('2026-08-15 09:00:00'));
    CustomerMerge::factory()->create([
        'anonymous_customer_id' => $anonymous->id,
        'customer_id' => $verified->id,
    ]);

    withIp('185.220.101.42');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($verified, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->kind)->toBe('verified')
        ->and($view->title)->toBe('hermione@example.com');

    expect(entityFact($view->facts, 'Identity')->value)->toBe('hermione@example.com')
        ->and(entityFact($view->facts, 'IPs')->value)->toBe('185.220.101.42')
        ->and(entityFact($view->facts, 'Merged from')->value)->toBe($anonymous->id.' (Aug 15, 2026)');
});

it('reads "—" for an actor with no merges and no ips', function (): void {
    $customer = $this->verifiedCustomer();
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect(entityFact($view->facts, 'IPs')->value)->toBe('—')
        ->and(entityFact($view->facts, 'Merged from')->value)->toBe('—')
        ->and(entityFact($view->facts, 'First channel')->value)->toBe('—')
        ->and($view->visits)->toBe([]);
});

it('lists an actor\'s visits, newest first, and names the earliest one\'s channel', function (): void {
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit($customer->id.'-one', $this->moment('2026-08-19 09:00:00'), '/art/starry-night', 'newsletter.example.com', 'newsletter', 'email', 'sept', null, null, $customer->id));
    $analytics->recordVisit(new AnalyticsVisit($customer->id.'-two', $this->moment('2026-08-20 09:00:00'), '/', null, null, null, null, null, null, $customer->id));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->visits)->toHaveCount(2)
        ->and($view->visits[0]->sessionId)->toBe($customer->id.'-two')
        ->and($view->visits[1]->sessionId)->toBe($customer->id.'-one')
        ->and(entityFact($view->facts, 'First channel')->value)->toBe('Email campaign: sept');
});

it('caps the visits panel at 20, newest first', function (): void {
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 25) as $i) {
        $analytics->recordVisit(new AnalyticsVisit(
            sprintf('%s-%02d', $customer->id, $i),
            $this->moment('2026-08-19 09:00:00')->modify("+{$i} minutes"),
            '/',
            null, null, null, null, null, null,
            $customer->id,
        ));
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->visits)->toHaveCount(20)
        ->and($view->visits[0]->sessionId)->toBe(sprintf('%s-25', $customer->id));
});

it('carries no visits on a listing\'s page — a visit belongs to a session, not a listing', function (): void {
    $listing = $this->listing($this->seller());
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $view = EntityActivity::forListing($listing, $range, null);

    expect($view->visits)->toBe([]);
});

it('titles an anonymous actor "Anonymous visitor"', function (): void {
    $customer = $this->anonymousCustomer();
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->kind)->toBe('anonymous')
        ->and($view->title)->toBe('Anonymous visitor');
});

it('flags an actor whose peak hour passes the threshold, with the hourly strip and flag text', function (): void {
    $seller = $this->seller();
    /** @var list<Listing> $listings */
    $listings = array_map(fn (): Listing => $this->listing($seller), range(1, 3));
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    withIp('185.220.101.42');
    foreach (range(1, 120) as $i) {
        $listing = $listings[$i % 3];
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 21:00:00')->modify("+{$i} seconds"), "peak-{$i}"));
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->flagged)->toBeTrue()
        ->and($view->flagText)->toBe(
            '120 listing views between 21:00 and 22:00 UTC on Aug 19 from 185.220.101.42, one every 30.0 seconds across 3 listings, no favorite or cart event in the range.',
        )
        ->and($view->stripTitle)->toBe('By hour, Aug 19')
        ->and($view->strip)->toHaveCount(24)
        ->and($view->strip[21]->hot)->toBeTrue()
        ->and($view->strip[0]->hot)->toBeFalse()
        ->and(entityTile($view->tiles, 'Peak per hour')->value)->toBe('120')
        ->and(entityTile($view->tiles, 'Peak per hour')->note)->toBe('Aug 19, 21:00 UTC');
});

it('drops the no-favorite-or-cart clause once the actor favorited or cart-added in the range', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    withIp('185.220.101.42');
    foreach (range(1, 100) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 21:00:00')->modify("+{$i} seconds"), "flag-{$i}"));
    }
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 22:30:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->flagged)->toBeTrue()
        ->and($view->flagText)->toEndWith('across 1 listings.')
        ->and($view->flagText)->not->toContain('no favorite or cart event');
});

it('filters the feed by event name and caps it, carrying the unfiltered total in its caption', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    foreach (range(1, 5) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment("2026-08-19 09:0{$i}:00"), "row-{$i}"));
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $all = EntityActivity::forListing($listing, $range, null);
    $filtered = EntityActivity::forListing($listing, $range, AnalyticsEventName::ListingFavorite);

    expect($all->feed)->toHaveCount(6)
        ->and($all->feedCaption)->toBe('6 of 6 shown, newest first')
        ->and($filtered->feed)->toHaveCount(1)
        ->and($filtered->feed[0]->name)->toBe('listing.favorite')
        ->and($filtered->feedCaption)->toBe('1 of 6 shown, newest first');
});

it('orders the feed newest first', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $first = $this->verifiedCustomer();
    $second = $this->verifiedCustomer();
    $third = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $first->id, $this->moment('2026-08-19 09:00:00'), 'first'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $third->id, $this->moment('2026-08-21 09:00:00'), 'third'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $second->id, $this->moment('2026-08-20 09:00:00'), 'second'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forListing($listing, $range, null);

    expect(array_column($view->feed, 'otherId'))->toBe([$third->id, $second->id, $first->id]);
});

it('names a deleted listing "listing no longer exists" on an actor\'s feed', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();
    $listingId = $listing->id;
    $listing->delete();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->feed)->toHaveCount(1)
        ->and($view->feed[0]->otherLabel)->toBe('listing no longer exists')
        ->and($view->feed[0]->otherId)->toBe($listingId)
        ->and($view->feed[0]->otherExists)->toBeFalse();
});

it('names an order subject "order {id}" on an actor\'s feed, linked, with its listing titles', function (): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller, ['title' => 'Starry Night']);
    $listingTwo = $this->listing($seller, ['title' => 'Snowy Owl']);
    $customer = $this->verifiedCustomer();

    $order = $this->orderFor($customer, $listingOne, $listingTwo);
    app(Analytics::class)->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, AnalyticsEventName::OrderPlace, $this->moment('2026-08-24 12:00:00'));

    expect($view->feed)->toHaveCount(1)
        ->and($view->feed[0]->otherLabel)->toBe("order {$order->id}")
        ->and($view->feed[0]->otherKind)->toBe('order')
        ->and($view->feed[0]->otherId)->toBe($order->id)
        ->and($view->feed[0]->otherExists)->toBeTrue()
        ->and($view->feed[0]->listingTitles)->toBe(['Starry Night', 'Snowy Owl']);
});

it('names a cart subject "cart {id}" on an actor\'s feed, unlinked, with its listing titles', function (): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller, ['title' => 'Starry Night']);
    $listingTwo = $this->listing($seller, ['title' => 'Snowy Owl']);
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forCart(
        AnalyticsEventName::CheckoutOpen,
        $cart->id,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
        ['listing_ids' => [$listingOne->id, $listingTwo->id]],
    ));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->feed)->toHaveCount(1)
        ->and($view->feed[0]->otherLabel)->toBe("cart {$cart->id}")
        ->and($view->feed[0]->otherKind)->toBe('cart')
        ->and($view->feed[0]->otherId)->toBe($cart->id)
        ->and($view->feed[0]->otherExists)->toBeFalse()
        ->and($view->feed[0]->listingTitles)->toBe(['Starry Night', 'Snowy Owl']);
});

it('names a store subject on an actor\'s feed, linked, by the store\'s name, with no listing titles', function (): void {
    $customer = $this->verifiedCustomer();
    $profile = StoreProfile::factory()->create(['name' => 'Weasleys\' Wizard Wheezes']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(
        AnalyticsEventName::StoreView,
        $profile->id,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
    ));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->feed)->toHaveCount(1)
        ->and($view->feed[0]->name)->toBe('store.view')
        ->and($view->feed[0]->verb)->toBe('opened')
        ->and($view->feed[0]->otherLabel)->toBe('Weasleys\' Wizard Wheezes')
        ->and($view->feed[0]->otherKind)->toBe('store')
        ->and($view->feed[0]->otherId)->toBe($profile->id)
        ->and($view->feed[0]->otherExists)->toBeTrue()
        ->and($view->feed[0]->listingTitles)->toBe([]);
});

it('names a deleted store "store no longer exists" on an actor\'s feed', function (): void {
    $customer = $this->verifiedCustomer();
    $profile = StoreProfile::factory()->create();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(
        AnalyticsEventName::StoreView,
        $profile->id,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
    ));
    $analytics->flush();
    $profile->delete();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->feed[0]->otherLabel)->toBe('store no longer exists')
        ->and($view->feed[0]->otherExists)->toBeFalse();
});

it('reads no listing titles for a listing subject, only for an order or cart subject', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Starry Night']);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forActor($customer, $range, null, $this->moment('2026-08-24 12:00:00'));

    expect($view->feed[0]->listingTitles)->toBe([]);
});

it('names an anonymous actor "Anonymous visitor" on a listing\'s feed', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forListing($listing, $range, null);

    expect($view->feed)->toHaveCount(1)
        ->and($view->feed[0]->otherLabel)->toBe('Anonymous visitor')
        ->and($view->feed[0]->otherKind)->toBe('actor')
        ->and($view->feed[0]->otherId)->toBe($customer->id);
});

it('carries the request id and icon path onto a feed row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    withIp('10.0.0.5');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $view = EntityActivity::forListing($listing, $range, null);

    expect($view->feed[0]->ip)->toBe('10.0.0.5')
        ->and($view->feed[0]->requestId)->toBe('req_01J00000000000000000000ABC')
        ->and($view->feed[0]->iconPath)->toBe(AnalyticsEventName::ListingView->iconPath())
        ->and($view->feed[0]->verb)->toBe('viewed');
});
