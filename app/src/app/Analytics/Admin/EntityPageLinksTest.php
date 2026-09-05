<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\AnalyticsEventName;

it('builds one range link per size, carrying other filters through, active on the current one', function (): void {
    $links = EntityPageLinks::range('admin.analytics.listings.show', 'listing', 'lst_1', ['event' => 'listing.view'], 30);

    expect($links)->toHaveCount(3);

    [$seven, $thirty, $ninety] = $links;

    expect($seven['label'])->toBe('7d')
        ->and($seven['href'])->toBe(route('admin.analytics.listings.show', ['listing' => 'lst_1', 'event' => 'listing.view', 'range' => 7]))
        ->and($seven['active'])->toBeFalse()
        ->and($thirty['active'])->toBeTrue()
        ->and($ninety['active'])->toBeFalse();
});

it('builds an "all" link plus one per event name, active on the current one', function (): void {
    $eventNames = [AnalyticsEventName::StoreView];

    $links = EntityPageLinks::event('admin.analytics.stores.show', 'store', 'sto_1', ['range' => '7'], AnalyticsEventName::StoreView, $eventNames);

    expect($links)->toHaveCount(2);

    [$all, $storeView] = $links;

    expect($all['label'])->toBe('All')
        ->and($all['href'])->toBe(route('admin.analytics.stores.show', ['store' => 'sto_1', 'range' => '7']))
        ->and($all['active'])->toBeFalse()
        ->and($storeView['label'])->toBe('Store views')
        ->and($storeView['href'])->toBe(route('admin.analytics.stores.show', ['store' => 'sto_1', 'range' => '7', 'event' => 'store.view']))
        ->and($storeView['active'])->toBeTrue();
});

it('marks "all" active when no event is current', function (): void {
    $links = EntityPageLinks::event('admin.analytics.listings.show', 'listing', 'lst_1', [], null, [AnalyticsEventName::ListingView]);

    expect($links[0]['active'])->toBeTrue();
});
