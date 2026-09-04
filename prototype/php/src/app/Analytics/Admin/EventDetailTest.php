<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\EventBreakdown;
use App\Domain\Analytics\PageViewSite;
use App\Models\Customer;

/**
 * @param  list<EventTile>  $tiles
 */
function eventTileLabelled(array $tiles, string $label): EventTile
{
    $found = collect($tiles)->firstWhere('label', $label);
    assert($found instanceof EventTile);

    return $found;
}

/**
 * @param  list<EventBreakdownRow>  $rows
 */
function eventRowNamed(array $rows, string $id): EventBreakdownRow
{
    $found = collect($rows)->firstWhere('id', $id);
    assert($found instanceof EventBreakdownRow);

    return $found;
}

it('carries the five range tiles for a seeded event', function (): void {
    $listing = $this->listing($this->seller());
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    // Current 7-day range (2026-08-18 .. 2026-08-24): 4 views, busiest on 08-19.
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 10:00:00'), 'b'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $anonymous->id, $this->moment('2026-08-20 09:00:00'), 'c'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-22 09:00:00'), 'd'));
    // Previous 7-day range (2026-08-11 .. 2026-08-17): 1 view.
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-15 09:00:00'), 'e'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $detail = EventDetail::forRange('listing.view', $range, EventBreakdown::Listing);

    expect($detail->name)->toBe('listing.view')
        ->and($detail->label)->toBe('Listing views')
        ->and($detail->tiles)->toHaveCount(5);

    [$thisRange, $previous, $change, $busiest, $actors] = $detail->tiles;

    expect($thisRange->label)->toBe('This range')->and($thisRange->value)->toBe('4')->and($thisRange->note)->toBe('7 days')
        ->and($previous->label)->toBe('Previous')->and($previous->value)->toBe('1')->and($previous->note)->toBe('the 7 days before')
        ->and($change->label)->toBe('Change')->and($change->value)->toBe('+300.0%')->and($change->note)->toBe('up on the previous range')
        ->and($busiest->label)->toBe('Busiest day')->and($busiest->value)->toBe('2')->and($busiest->note)->toBe('Aug 19')
        ->and($actors->label)->toBe('Distinct actors')->and($actors->value)->toBe('2')->and($actors->note)->toBe('1 anonymous');
});

it('lays the daily series out oldest first, over the current range only', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 10:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-22 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $detail = EventDetail::forRange('listing.view', $range, EventBreakdown::Listing);

    expect($detail->daily)->toBe([0, 2, 1, 0, 1, 0, 0])
        ->and($detail->firstDay)->toBe('Aug 18')
        ->and($detail->lastDay)->toBe('Aug 24');
});

it('breaks down by listing, ordered by current desc, and reads a deleted listing as gone', function (): void {
    $seller = $this->seller();
    $keptA = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $keptB = $this->listing($seller, ['title' => 'Weasley Wireless']);
    $deleted = $this->listing($seller, ['title' => 'Chamber Sketch']);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $keptA->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "a{$i}"));
    }
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $keptB->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'b1'));
    foreach (range(1, 2) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $deleted->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "d{$i}"));
    }
    $analytics->flush();
    $deletedId = $deleted->id;
    $deleted->delete();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $detail = EventDetail::forRange('listing.favorite', $range, EventBreakdown::Listing);

    expect($detail->breakdown)->toBe(EventBreakdown::Listing)
        ->and($detail->rows)->toHaveCount(3);

    [$first, $second, $third] = $detail->rows;

    expect($first->id)->toBe($keptA->id)
        ->and($first->title)->toBe('The Burrow at Dusk · '.$seller->displayName())
        ->and($first->current)->toBe(3)
        ->and($first->sharePercent)->toBe('50%')
        ->and($first->shareWidth)->toBe(50)
        ->and($second->id)->toBe($deletedId)
        ->and($second->title)->toBe('listing no longer exists')
        ->and($second->current)->toBe(2)
        ->and($third->id)->toBe($keptB->id)
        ->and($third->title)->toBe('Weasley Wireless · '.$seller->displayName())
        ->and($third->current)->toBe(1);
});

it('breaks down by actor, carrying identity kind and who', function (): void {
    $listing = $this->listing($this->seller());
    $hermione = Customer::factory()->create(['email' => 'hermione@example.com']);
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00'), "h{$i}"));
    }
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $anonymous->id, $this->moment('2026-08-19 09:00:00'), 'anon1'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $detail = EventDetail::forRange('listing.cart_add', $range, EventBreakdown::Actor);

    expect($detail->breakdown)->toBe(EventBreakdown::Actor)
        ->and($detail->rows)->toHaveCount(2);

    [$first, $second] = $detail->rows;

    expect($first->id)->toBe($hermione->id)
        ->and($first->actorKind)->toBe('verified')
        ->and($first->title)->toBe('hermione@example.com')
        ->and($first->current)->toBe(3)
        ->and($second->id)->toBe($anonymous->id)
        ->and($second->actorKind)->toBe('anonymous')
        ->and($second->title)->toBe('never signed in')
        ->and($second->current)->toBe(1);
});

it('breaks down help.answered by article, labelled by slug', function (): void {
    $sellerOne = $this->seller('Weasley Studio');
    $sellerTwo = $this->seller('Ollivanders');
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forHelpArticle(AnalyticsEventName::HelpAnswered, 'printing-a-label-from-an-order', $sellerOne->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forHelpArticle(AnalyticsEventName::HelpAnswered, 'printing-a-label-from-an-order', $sellerTwo->id, $this->moment('2026-08-19 09:00:00'), 'b'));
    $analytics->recordEvent(AnalyticsEvent::forHelpArticle(AnalyticsEventName::HelpAnswered, 'when-money-reaches-your-account', $sellerOne->id, $this->moment('2026-08-19 09:00:00'), 'c'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $detail = EventDetail::forRange('help.answered', $range, EventBreakdown::Article);

    expect($detail->breakdown)->toBe(EventBreakdown::Article)
        ->and($detail->rows)->toHaveCount(2);

    [$first, $second] = $detail->rows;

    expect($first->id)->toBe('printing-a-label-from-an-order')
        ->and($first->title)->toBe('printing-a-label-from-an-order')
        ->and($first->current)->toBe(2)
        ->and($second->id)->toBe('when-money-reaches-your-account')
        ->and($second->title)->toBe('when-money-reaches-your-account')
        ->and($second->current)->toBe(1);
});

it('reads page.view from the roll-up, always by pattern, with no actor tile', function (): void {
    $analytics = app(Analytics::class);

    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-19 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-19 15:00:00'));
    $analytics->recordPageView(PageViewSite::Seller, '/seller/dashboard', $this->moment('2026-08-20 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-15 09:00:00'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    // A stray by=listing request still reads page.view by pattern.
    $detail = EventDetail::forRange('page.view', $range, EventBreakdown::Listing);

    expect($detail->label)->toBe('Page views')
        ->and($detail->breakdown)->toBe(EventBreakdown::Pattern);

    $actorsTile = eventTileLabelled($detail->tiles, 'Distinct actors');
    expect($actorsTile->value)->toBe('—')
        ->and($actorsTile->note)->toBe('—');

    expect($detail->rows)->toHaveCount(2);
    $art = eventRowNamed($detail->rows, '/art/{listing}');
    $dashboard = eventRowNamed($detail->rows, '/seller/dashboard');

    expect($art->site)->toBe(PageViewSite::Shop)
        ->and($art->current)->toBe(2)
        ->and($art->actorKind)->toBeNull()
        ->and($dashboard->site)->toBe(PageViewSite::Seller)
        ->and($dashboard->current)->toBe(1);
});

it('reads a name with nothing recorded as all zeroes and no rows', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $detail = EventDetail::forRange('listing.unfavorite', $range, EventBreakdown::Listing);

    $change = eventTileLabelled($detail->tiles, 'Change');

    expect($detail->daily)->toBe([0, 0, 0, 0, 0, 0, 0])
        ->and($change->value)->toBe('')
        ->and($detail->rows)->toBe([]);
});
