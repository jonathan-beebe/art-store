<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('names the storefront interactions the analytics store accepts', function (): void {
    expect(array_column(AnalyticsEventName::cases(), 'value'))
        ->toBe([
            'listing.view', 'listing.favorite', 'listing.unfavorite', 'listing.cart_add',
            'checkout.open', 'order.place', 'order.pay', 'order.cancel',
        ]);
});

it('labels every case', function (AnalyticsEventName $name, string $expected): void {
    expect($name->label())->toBe($expected);
})->with([
    'view' => [AnalyticsEventName::ListingView, 'View'],
    'favorite' => [AnalyticsEventName::ListingFavorite, 'Favorite'],
    'unfavorite' => [AnalyticsEventName::ListingUnfavorite, 'Unfavorite'],
    'cart_add' => [AnalyticsEventName::ListingCartAdd, 'Cart add'],
    'checkout_open' => [AnalyticsEventName::CheckoutOpen, 'Checkout opened'],
    'order_place' => [AnalyticsEventName::OrderPlace, 'Order placed'],
    'order_pay' => [AnalyticsEventName::OrderPay, 'Order paid'],
    'order_cancel' => [AnalyticsEventName::OrderCancel, 'Order cancelled'],
]);

it('gives every case a feed-row verb', function (AnalyticsEventName $name, string $expected): void {
    expect($name->verb())->toBe($expected);
})->with([
    'view' => [AnalyticsEventName::ListingView, 'viewed'],
    'favorite' => [AnalyticsEventName::ListingFavorite, 'favorited'],
    'unfavorite' => [AnalyticsEventName::ListingUnfavorite, 'unfavorited'],
    'cart_add' => [AnalyticsEventName::ListingCartAdd, 'added to cart'],
    'checkout_open' => [AnalyticsEventName::CheckoutOpen, 'opened checkout'],
    'order_place' => [AnalyticsEventName::OrderPlace, 'placed an order'],
    'order_pay' => [AnalyticsEventName::OrderPay, 'paid an order'],
    'order_cancel' => [AnalyticsEventName::OrderCancel, 'cancelled an order'],
]);

it('gives every case a plural label', function (AnalyticsEventName $name, string $expected): void {
    expect($name->pluralLabel())->toBe($expected);
})->with([
    'view' => [AnalyticsEventName::ListingView, 'Listing views'],
    'favorite' => [AnalyticsEventName::ListingFavorite, 'Favorites'],
    'unfavorite' => [AnalyticsEventName::ListingUnfavorite, 'Unfavorites'],
    'cart_add' => [AnalyticsEventName::ListingCartAdd, 'Cart adds'],
    'checkout_open' => [AnalyticsEventName::CheckoutOpen, 'Checkouts opened'],
    'order_place' => [AnalyticsEventName::OrderPlace, 'Orders placed'],
    'order_pay' => [AnalyticsEventName::OrderPay, 'Orders paid'],
    'order_cancel' => [AnalyticsEventName::OrderCancel, 'Orders cancelled'],
]);

it('gives every case a non-empty icon path', function (AnalyticsEventName $name): void {
    expect($name->iconPath())->not->toBe('');
})->with(AnalyticsEventName::cases());
