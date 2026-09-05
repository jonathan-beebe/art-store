<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use InvalidArgumentException;

it('reads an ordered list of known event names into their enum cases', function (): void {
    $definition = FunnelDefinition::of(['listing.view', 'listing.cart_add']);

    expect($definition->steps)->toBe([AnalyticsEventName::ListingView, AnalyticsEventName::ListingCartAdd]);
});

it('refuses fewer than two steps', function (): void {
    FunnelDefinition::of(['listing.view']);
})->throws(InvalidArgumentException::class);

it('refuses an empty list', function (): void {
    FunnelDefinition::of([]);
})->throws(InvalidArgumentException::class);

it('refuses a name outside the analytics vocabulary', function (): void {
    FunnelDefinition::of(['listing.view', 'listing.teleport']);
})->throws(InvalidArgumentException::class);

it('refuses a repeated name', function (): void {
    FunnelDefinition::of(['listing.view', 'listing.cart_add', 'listing.view']);
})->throws(InvalidArgumentException::class);

it('builds the storefront default: view, cart add, checkout, place, pay, favorites left out', function (): void {
    $definition = FunnelDefinition::storefront();

    expect($definition->steps)->toBe([
        AnalyticsEventName::ListingView,
        AnalyticsEventName::ListingCartAdd,
        AnalyticsEventName::CheckoutOpen,
        AnalyticsEventName::OrderPlace,
        AnalyticsEventName::OrderPay,
    ]);
});
