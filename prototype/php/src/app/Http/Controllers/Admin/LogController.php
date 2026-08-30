<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LogsQueryRequest;
use App\Logging\Admin\LogRowQuery;
use App\Logging\Admin\LogStoryHeader;
use App\Logging\LogDomain;
use App\Logging\LogStore;
use App\Logging\StoryEvent;
use App\Logging\StoryLevel;
use App\Logging\StoryPhase;
use App\Support\Page;
use Illuminate\View\View;

/**
 * `/admin/logs` and its per-request story view — docs/logging.md § "Viewer"
 * and § "The story view". Every read goes through `App\Logging\Admin\LogRowQuery`
 * over `App\Logging\LogStore::$connection`, so a disabled store (`null`
 * connection) is the one branch every action takes before touching it.
 */
final class LogController extends Controller
{
    private const int ROWS_PER_PAGE = 50;

    /** What each level's stat tile is titled, in display order. */
    private const array LEVEL_TILES = [
        'error' => 'Errors',
        'warn' => 'Warnings',
        'info' => 'Info',
        'debug' => 'Debug',
    ];

    public function index(LogsQueryRequest $request): View
    {
        $store = app(LogStore::class);
        $roundTripped = $request->roundTrippedFilters();

        if ($store->connection === null) {
            return view('admin.logs.index', [
                'storeAvailable' => false,
                'filters' => $roundTripped,
            ]);
        }

        $query = new LogRowQuery($store->connection);
        $filters = $request->filters();
        $grouped = $request->grouped();

        $totalCount = $grouped ? $query->countGroups($filters) : $query->count($filters);
        $page = Page::of($request->page(), self::ROWS_PER_PAGE, $totalCount);

        return view('admin.logs.index', [
            'storeAvailable' => true,
            'grouped' => $grouped,
            'lines' => $grouped ? [] : $query->rows($filters, $page->limit, $page->offset),
            'groups' => $grouped ? $query->groups($filters, $page->limit, $page->offset) : [],
            'tiles' => $this->levelTiles($query->levelTallies($filters), $roundTripped),
            'filters' => $roundTripped,
            'domains' => LogDomain::cases(),
            'levels' => StoryLevel::cases(),
            'phases' => StoryPhase::cases(),
            'events' => StoryEvent::cases(),
            'page' => $page,
            'filterQuery' => http_build_query($roundTripped),
        ]);
    }

    public function show(string $requestId): View
    {
        $store = app(LogStore::class);

        if ($store->connection === null) {
            return view('admin.logs.show', [
                'storeAvailable' => false,
                'requestId' => $requestId,
                'lines' => [],
                'totalCount' => 0,
                'lineCap' => LogRowQuery::STORY_LINE_CAP,
                'header' => LogStoryHeader::empty(),
            ]);
        }

        $query = new LogRowQuery($store->connection);
        $lines = $query->storyRows($requestId);
        $totalCount = count($lines) < LogRowQuery::STORY_LINE_CAP
            ? count($lines)
            : $query->storyCount($requestId);

        return view('admin.logs.show', [
            'storeAvailable' => true,
            'requestId' => $requestId,
            'lines' => $lines,
            'totalCount' => $totalCount,
            'lineCap' => LogRowQuery::STORY_LINE_CAP,
            'header' => LogStoryHeader::of($lines),
        ]);
    }

    /**
     * The four tiles, each linking to the same query with `level` set.
     *
     * @param  array<string, int>  $tallies
     * @param  array<string, string>  $roundTripped
     * @return list<array{level: string, label: string, count: int, href: string}>
     */
    private function levelTiles(array $tallies, array $roundTripped): array
    {
        $withoutLevel = collect($roundTripped)->except('level')->all();

        $tiles = [];
        foreach (self::LEVEL_TILES as $level => $label) {
            $tiles[] = [
                'level' => $level,
                'label' => $label,
                'count' => $tallies[$level] ?? 0,
                'href' => route('admin.logs.index', [...$withoutLevel, 'level' => $level]),
            ];
        }

        return $tiles;
    }
}
