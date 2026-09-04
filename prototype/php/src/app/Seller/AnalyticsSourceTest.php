<?php

declare(strict_types=1);

namespace App\Seller;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Seller\ActivityKind;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

it('turns a view, a favorite, an unfavorite, and a cart add into browse rows naming the listing, newest first', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $scope = FeedScope::forCustomer($seller, $customer);
    $analytics = new Analytics;

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 10:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingUnfavorite, $listing->id, $customer->id, $this->moment('2026-08-19 11:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-19 12:00:00')));
    $analytics->flush();

    $events = (new AnalyticsSource)->events($scope);

    expect($events)->toHaveCount(4);

    foreach ($events as $event) {
        expect($event->kind)->toBe(ActivityKind::Browse)
            ->and($event->link)->toBe(route('seller.listings.show', $listing->id));
    }

    expect($events[0]->text)->toBe('added The Burrow at Dusk to their cart')
        ->and($events[1]->text)->toBe('took The Burrow at Dusk out of their favorites')
        ->and($events[2]->text)->toBe('favorited The Burrow at Dusk')
        ->and($events[3]->text)->toBe('viewed The Burrow at Dusk');
});

it('turns a checkout.open event naming one of the seller\'s listings into an "opened checkout" row', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $scope = FeedScope::forCustomer($seller, $customer);
    $cart = $this->cartFor($customer);
    $analytics = new Analytics;

    $analytics->recordEvent(AnalyticsEvent::forCart(
        AnalyticsEventName::CheckoutOpen,
        $cart->id,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
        ['listing_ids' => [$listing->id]],
    ));
    $analytics->flush();

    $events = (new AnalyticsSource)->events($scope);

    expect($events)->toHaveCount(1)
        ->and($events[0]->text)->toBe('opened checkout')
        ->and($events[0]->kind)->toBe(ActivityKind::Browse)
        ->and($events[0]->link)->toBe(route('seller.listings.show', $listing->id));
});

it('leaves out a checkout.open event that names only another seller\'s listing', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Lovegood Curiosities');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $otherListing = $this->listing($other, ['title' => 'Nine Owls']);
    $scope = FeedScope::forCustomer($seller, $customer);
    $cart = $this->cartFor($customer);
    $analytics = new Analytics;

    $analytics->recordEvent(AnalyticsEvent::forCart(
        AnalyticsEventName::CheckoutOpen,
        $cart->id,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
        ['listing_ids' => [$otherListing->id]],
    ));
    $analytics->flush();

    expect((new AnalyticsSource)->events($scope))->toBe([]);
});

it('leaves out a listing view on another seller\'s listing', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $other = $this->seller('Lovegood Curiosities');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $otherListing = $this->listing($other, ['title' => 'Nine Owls']);
    $scope = FeedScope::forCustomer($seller, $customer);
    $analytics = new Analytics;

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $otherListing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    expect((new AnalyticsSource)->events($scope))->toBe([]);
});

it('leaves out an event by another customer', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $otherCustomer = Customer::factory()->create(['name' => 'Hermione Granger']);
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $scope = FeedScope::forCustomer($seller, $customer);
    $analytics = new Analytics;

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $otherCustomer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    expect((new AnalyticsSource)->events($scope))->toBe([]);
});

it('returns no rows for an empty listingIds scope, and runs no query beyond the guard', function (): void {
    $seller = $this->seller('Empty Shop');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $scope = FeedScope::forCustomer($seller, $customer);

    DB::connection('analytics')->enableQueryLog();

    $events = (new AnalyticsSource)->events($scope);

    expect($events)->toBe([])
        ->and(DB::connection('analytics')->getQueryLog())->toBe([]);
});
