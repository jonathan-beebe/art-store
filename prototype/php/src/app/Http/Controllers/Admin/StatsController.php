<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\PageViewDay;
use App\Domain\Analytics\PageViewWeek;
use App\Domain\Reports\ListingEventTally;
use App\Http\Controllers\Controller;
use App\Models\ListingEvent;
use App\Models\PageViewCount;
use Illuminate\View\View;

final class StatsController extends Controller
{
    public function __invoke(): View
    {
        $week = PageViewWeek::endingOn(PageViewDay::of($this->now()));

        return view('admin.stats.index', [
            'days' => PageViewCount::totalsByDay($week),
            'patterns' => PageViewCount::totalsByPattern(),
            'events' => ListingEventTally::from(ListingEvent::platformCountsByType()),
        ]);
    }
}
