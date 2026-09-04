<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\AnalyticsRange;
use DateTimeImmutable;

it('offers one link per range the page reads over', function (): void {
    $links = DashboardChrome::rangeLinks(AnalyticsRange::of(30, new DateTimeImmutable('2026-09-04')));

    expect(array_map(fn (NavLink $link): string => $link->label, $links))
        ->toBe(['7 days', '30 days', '90 days']);
});

it('marks the range in force and leaves the others open', function (int $days, int $activeIndex): void {
    $links = DashboardChrome::rangeLinks(AnalyticsRange::of($days, new DateTimeImmutable('2026-09-04')));

    expect(array_map(fn (NavLink $link): bool => $link->active, $links))
        ->toBe([$activeIndex === 0, $activeIndex === 1, $activeIndex === 2]);
})->with([
    'a week' => [7, 0],
    'a month' => [30, 1],
    'a quarter' => [90, 2],
]);

it('sends each link back to the dashboard carrying its own range', function (): void {
    $links = DashboardChrome::rangeLinks(AnalyticsRange::of(7, new DateTimeImmutable('2026-09-04')));

    expect($links[2]->href)->toBe(route('seller.dashboard', ['range' => 90]));
});
