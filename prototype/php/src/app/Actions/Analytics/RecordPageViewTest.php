<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;

it('inserts the first hit of a day', function (): void {
    app(RecordPageView::class)(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-24 09:00:00'));

    $row = PageViewCount::query()->sole();

    expect($row->site)->toBe(PageViewSite::Shop->value)
        ->and($row->path_pattern)->toBe('/art/{listing}')
        ->and($row->day)->toBe('2026-08-24')
        ->and($row->count)->toBe(1);
});

it('increments the same row on a later hit rather than inserting a second one', function (): void {
    $record = app(RecordPageView::class);
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-24 09:00:00'));
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-24 17:00:00'));

    $row = PageViewCount::query()->sole();

    expect($row->count)->toBe(2);
});

it('keeps a different day, site, or pattern as a row of its own', function (): void {
    $record = app(RecordPageView::class);
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-24 09:00:00'));
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-25 09:00:00'));
    $record(PageViewSite::Seller, '/art/{listing}', $this->moment('2026-08-24 09:00:00'));
    $record(PageViewSite::Shop, '/seller/listings/{listing}', $this->moment('2026-08-24 09:00:00'));

    expect(PageViewCount::query()->count())->toBe(4);
});
