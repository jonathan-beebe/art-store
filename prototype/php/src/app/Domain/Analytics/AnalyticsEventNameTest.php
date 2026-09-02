<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('names the storefront interactions the analytics store accepts', function (): void {
    expect(array_column(AnalyticsEventName::cases(), 'value'))
        ->toBe(['listing.view', 'listing.favorite', 'listing.unfavorite', 'listing.cart_add']);
});

it('labels every case', function (AnalyticsEventName $name, string $expected): void {
    expect($name->label())->toBe($expected);
})->with([
    'view' => [AnalyticsEventName::ListingView, 'View'],
    'favorite' => [AnalyticsEventName::ListingFavorite, 'Favorite'],
    'unfavorite' => [AnalyticsEventName::ListingUnfavorite, 'Unfavorite'],
    'cart_add' => [AnalyticsEventName::ListingCartAdd, 'Cart add'],
]);
