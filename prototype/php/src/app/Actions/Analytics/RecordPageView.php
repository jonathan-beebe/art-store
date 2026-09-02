<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Analytics\AnalyticsWriteGuard;
use App\Domain\Analytics\PageViewDay;
use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Adds one hit to the count for a site, a route pattern, and a day. The
 * unique index on those three columns is what makes the first hit of a day
 * an insert and every later one an increment, in one statement and no read.
 * Runs behind {@see AnalyticsWriteGuard}, so a failure of the analytics
 * store never reaches the request that triggered the count.
 */
final readonly class RecordPageView
{
    public function __invoke(PageViewSite $site, string $pathPattern, DateTimeImmutable $now): void
    {
        AnalyticsWriteGuard::attempt(fn (): int => PageViewCount::query()->upsert(
            [
                'site' => $site->value,
                'path_pattern' => $pathPattern,
                'day' => PageViewDay::of($now),
                'count' => 1,
            ],
            ['site', 'path_pattern', 'day'],
            ['count' => DB::raw('page_view_counts.count + 1')],
        ));
    }
}
