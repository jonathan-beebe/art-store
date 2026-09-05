<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Analytics\PageViewSite;
use App\Domain\Analytics\PageViewWeek;

it('sums a week, leaving a day outside the window out', function (): void {
    PageViewCount::factory()->create(['day' => '2026-08-17', 'count' => 5]);
    PageViewCount::factory()->create(['day' => '2026-08-18', 'count' => 2]);
    PageViewCount::factory()->create(['day' => '2026-08-24', 'count' => 3]);

    expect(PageViewCount::totalForWeek(PageViewWeek::endingOn('2026-08-24')))->toBe(5);
});

it('totals every day inside the window, newest first', function (): void {
    PageViewCount::factory()->create(['site' => PageViewSite::Shop->value, 'path_pattern' => '/art/{listing}', 'day' => '2026-08-18', 'count' => 2]);
    PageViewCount::factory()->create(['site' => PageViewSite::Seller->value, 'path_pattern' => '/seller', 'day' => '2026-08-18', 'count' => 1]);
    PageViewCount::factory()->create(['site' => PageViewSite::Shop->value, 'path_pattern' => '/art/{listing}', 'day' => '2026-08-24', 'count' => 4]);

    expect(PageViewCount::totalsByDay(PageViewWeek::endingOn('2026-08-24')))->toBe([
        ['day' => '2026-08-24', 'count' => 4],
        ['day' => '2026-08-18', 'count' => 3],
    ]);
});

it('totals every route pattern, busiest first', function (): void {
    PageViewCount::factory()->create(['site' => PageViewSite::Shop->value, 'path_pattern' => '/art/{listing}', 'day' => '2026-08-18', 'count' => 2]);
    PageViewCount::factory()->create(['site' => PageViewSite::Shop->value, 'path_pattern' => '/art/{listing}', 'day' => '2026-08-24', 'count' => 5]);
    PageViewCount::factory()->create(['site' => PageViewSite::Seller->value, 'path_pattern' => '/seller', 'day' => '2026-08-24', 'count' => 1]);

    expect(PageViewCount::totalsByPattern())->toBe([
        ['site' => PageViewSite::Shop->value, 'pathPattern' => '/art/{listing}', 'count' => 7],
        ['site' => PageViewSite::Seller->value, 'pathPattern' => '/seller', 'count' => 1],
    ]);
});
