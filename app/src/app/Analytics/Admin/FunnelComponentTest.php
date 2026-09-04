<?php

declare(strict_types=1);

use App\Analytics\Admin\FunnelStep;
use App\Analytics\Admin\FunnelView;
use App\Domain\Analytics\ChangeDirection;
use App\Domain\Analytics\FunnelRate;
use App\Domain\Analytics\RangeChange;

it('renders one cell per step', function (): void {
    $view = new FunnelView([
        new FunnelStep('visitors', 'Visitors', 344, 116, RangeChange::between(344, 116), null, 100, 100, false),
        new FunnelStep('listing.view', 'Viewed a listing', 281, 92, RangeChange::between(281, 92), FunnelRate::of(281, 344, 'visitors'), 82, 79, false),
        new FunnelStep('listing.cart_add', 'Added to cart', 128, 42, RangeChange::between(128, 42), FunnelRate::of(128, 281, 'viewed'), 37, 36, true),
    ]);

    $html = (string) $this->blade('<x-admin.analytics.funnel :funnel="$funnel" />', ['funnel' => $view]);

    expect(substr_count($html, 'Visitors'))->toBe(1)
        ->and($html)->toContain('Viewed a listing')
        ->and($html)->toContain('Added to cart');
});

it('shows the largest-drop badge on the flagged step only', function (): void {
    $view = new FunnelView([
        new FunnelStep('visitors', 'Visitors', 100, 100, RangeChange::between(100, 100), null, 100, 100, false),
        new FunnelStep('listing.view', 'Viewed a listing', 80, 80, RangeChange::between(80, 80), FunnelRate::of(80, 100, 'visitors'), 80, 80, false),
        new FunnelStep('listing.cart_add', 'Added to cart', 10, 40, RangeChange::between(10, 40), FunnelRate::of(10, 80, 'viewed'), 10, 40, true),
    ]);

    $html = (string) $this->blade('<x-admin.analytics.funnel :funnel="$funnel" />', ['funnel' => $view]);

    expect(substr_count($html, 'largest drop'))->toBe(1);
});

it('draws both bars at the step\'s own shares', function (): void {
    $view = new FunnelView([
        new FunnelStep('visitors', 'Visitors', 344, 116, RangeChange::between(344, 116), null, 100, 100, false),
        new FunnelStep('listing.view', 'Viewed a listing', 281, 92, RangeChange::between(281, 92), FunnelRate::of(281, 344, 'visitors'), 82, 79, false),
    ]);

    $html = (string) $this->blade('<x-admin.analytics.funnel :funnel="$funnel" />', ['funnel' => $view]);

    expect($html)->toContain('width: 82%')
        ->and($html)->toContain('width: 79%')
        ->and($html)->toContain('title="this range 281 · previous 92"');
});

it('renders the footer as a share of the prerequisite, and of visitors when the prerequisite is not visitors', function (): void {
    $view = new FunnelView([
        new FunnelStep('visitors', 'Visitors', 344, 116, RangeChange::between(344, 116), null, 100, 100, false),
        new FunnelStep('listing.view', 'Viewed a listing', 281, 92, RangeChange::between(281, 92), FunnelRate::of(281, 344, 'visitors'), 82, 79, false),
        new FunnelStep('listing.cart_add', 'Added to cart', 128, 42, RangeChange::between(128, 42), FunnelRate::of(128, 281, 'viewed'), 37, 36, false),
    ]);

    $html = (string) $this->blade('<x-admin.analytics.funnel :funnel="$funnel" />', ['funnel' => $view]);

    expect($html)->toContain('82% of visitors')
        ->and($html)->toContain('46% of viewed · 37% of visitors');
});

it('renders the side and note lines when present, and neither otherwise', function (): void {
    $view = new FunnelView([
        new FunnelStep('visitors', 'Visitors', 344, 116, RangeChange::between(344, 116), null, 100, 100, false),
        new FunnelStep('listing.view', 'Viewed a listing', 281, 92, RangeChange::between(281, 92), FunnelRate::of(281, 344, 'visitors'), 82, 79, false, side: '98 favorited'),
        new FunnelStep('order.pay', 'Paid', 19, 5, RangeChange::between(19, 5), FunnelRate::of(19, 25, 'placed'), 6, 4, false, note: '6 cancelled'),
    ]);

    $html = (string) $this->blade('<x-admin.analytics.funnel :funnel="$funnel" />', ['funnel' => $view]);

    expect($html)->toContain('98 favorited')
        ->and($html)->toContain('6 cancelled');
});

it('shows the up glyph in green and the down glyph in red', function (): void {
    $view = new FunnelView([
        new FunnelStep('visitors', 'Visitors', 200, 100, RangeChange::between(200, 100), null, 100, 100, false),
        new FunnelStep('listing.view', 'Viewed a listing', 40, 100, RangeChange::between(40, 100), FunnelRate::of(40, 200, 'visitors'), 20, 100, false),
    ]);

    expect($view->steps[0]->change->direction)->toBe(ChangeDirection::Up)
        ->and($view->steps[1]->change->direction)->toBe(ChangeDirection::Down);

    $html = (string) $this->blade('<x-admin.analytics.funnel :funnel="$funnel" />', ['funnel' => $view]);

    expect($html)->toContain('text-green-700 dark:text-green-400')
        ->and($html)->toContain('text-red-700 dark:text-red-500');
});
