<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Analytics\AnalyticsEventName;

it('mints a prefixed ulid', function (): void {
    $funnel = Funnel::factory()->create();

    expect($funnel->id)->toStartWith('fnl_');
});

it('reads its stored step names as their enum cases, in order', function (): void {
    $funnel = Funnel::factory()->create(['steps' => ['listing.view', 'checkout.open', 'order.pay']]);

    expect($funnel->steps())->toBe([
        AnalyticsEventName::ListingView,
        AnalyticsEventName::CheckoutOpen,
        AnalyticsEventName::OrderPay,
    ]);
});

it('keeps the raw stored names on the steps attribute', function (): void {
    $funnel = Funnel::factory()->create(['steps' => ['listing.view', 'listing.cart_add']]);

    expect($funnel->steps)->toBe(['listing.view', 'listing.cart_add']);
});
