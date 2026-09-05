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
use Illuminate\Http\RedirectResponse;
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

    /** What each level chip is titled, in display order — the four also
     * double as the level filter, so a chip's own href sets it. */
    private const array LEVEL_LABELS = [
        'error' => 'Errors',
        'warn' => 'Warnings',
        'info' => 'Info',
        'debug' => 'Debug',
    ];

    /** A landing visit — no query string at all — opens on the shop
     * domain, grouped by request: the view a founder means by "the log
     * viewer". Any query parameter present, even an empty one, is a
     * deliberate visit and skips this. */
    private const array DEFAULT_LANDING_QUERY = ['domain' => 'shop', 'group' => '1'];

    /** The filters `More filters` holds, once domain/level/event have their
     * own primary controls and health/viewer have their own quiet strip
     * affordances — what an inactive/active indicator on the disclosure is
     * computed over. */
    private const array MORE_FILTER_FIELDS = ['phase', 'request', 'txn', 'session', 'actor', 'msg', 'from', 'to', 'key', 'value', 'health', 'viewer', 'mcp'];

    /** Labels for the applied-state strip's removable chips, in the order
     * they appear. `key`/`value` render as one combined chip. */
    private const array CHIP_LABELS = [
        'domain' => 'domain',
        'level' => 'level',
        'event' => 'event',
        'phase' => 'phase',
        'request' => 'request',
        'txn' => 'txn',
        'session' => 'session',
        'actor' => 'actor',
        'msg' => 'message',
        'from' => 'from',
        'to' => 'to',
    ];

    public function index(LogsQueryRequest $request): View|RedirectResponse
    {
        if ($request->query() === []) {
            return redirect()->route('admin.logs.index', self::DEFAULT_LANDING_QUERY);
        }

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
            'levelChips' => $this->levelChips($query->levelTallies($filters), $roundTripped),
            'domainLinks' => $this->domainLinks($roundTripped),
            'viewLinks' => $this->viewLinks($roundTripped, $grouped),
            'activeFilterChips' => $this->activeFilterChips($roundTripped),
            'moreFiltersActive' => $this->moreFiltersActive($roundTripped),
            'healthToggle' => $this->toggleAffordance($roundTripped, 'health'),
            'viewerToggle' => $this->toggleAffordance($roundTripped, 'viewer'),
            'mcpToggle' => $this->toggleAffordance($roundTripped, 'mcp'),
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
     * The four level chips, each linking to the same query with `level`
     * set — they double as the level filter's fast path, replacing the
     * old separate stat-tile strip. The already-active chip links back to
     * the query with `level` removed instead, so tapping it again clears
     * the filter — the same toggle `toggleAffordance()` gives health/viewer.
     *
     * @param  array<string, int>  $tallies
     * @param  array<string, string>  $roundTripped
     * @return list<array{level: string, label: string, count: int, href: string, active: bool}>
     */
    private function levelChips(array $tallies, array $roundTripped): array
    {
        $withoutLevel = collect($roundTripped)->except('level')->all();
        $current = $roundTripped['level'] ?? null;

        $chips = [];
        foreach (self::LEVEL_LABELS as $level => $label) {
            $active = $current === $level;
            $chips[] = [
                'level' => $level,
                'label' => $label,
                'count' => $tallies[$level] ?? 0,
                'href' => route('admin.logs.index', $active ? $withoutLevel : [...$withoutLevel, 'level' => $level]),
                'active' => $active,
            ];
        }

        return $chips;
    }

    /**
     * The domain segmented control: "All" clears the filter, each named
     * site sets it, every other filter carried through unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function domainLinks(array $roundTripped): array
    {
        $withoutDomain = collect($roundTripped)->except('domain')->all();
        $current = $roundTripped['domain'] ?? null;

        $links = [[
            'label' => 'All',
            'href' => route('admin.logs.index', $withoutDomain),
            'active' => $current === null,
        ]];

        foreach (LogDomain::cases() as $domain) {
            $links[] = [
                'label' => $domain->value,
                'href' => route('admin.logs.index', [...$withoutDomain, 'domain' => $domain->value]),
                'active' => $current === $domain->value,
            ];
        }

        return $links;
    }

    /**
     * The Requests/Lines view toggle: `group=1` versus its absence, every
     * other filter carried through unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function viewLinks(array $roundTripped, bool $grouped): array
    {
        $withoutGroup = collect($roundTripped)->except('group')->all();

        return [
            [
                'label' => 'Requests',
                'href' => route('admin.logs.index', [...$withoutGroup, 'group' => '1']),
                'active' => $grouped,
            ],
            [
                'label' => 'Lines',
                'href' => route('admin.logs.index', $withoutGroup),
                'active' => ! $grouped,
            ],
        ];
    }

    /**
     * The applied-state strip's removable chips — every primary and
     * More-filters value currently set, each linking to the same query
     * with itself removed. `key`/`value` collapse into one chip since a
     * `value` never applies without a `key`.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, text: string, href: string}>
     */
    private function activeFilterChips(array $roundTripped): array
    {
        $chips = [];

        foreach (self::CHIP_LABELS as $field => $label) {
            if (! array_key_exists($field, $roundTripped)) {
                continue;
            }

            $chips[] = [
                'label' => $label,
                'text' => "{$label}: {$roundTripped[$field]}",
                'href' => route('admin.logs.index', collect($roundTripped)->except($field)->all()),
            ];
        }

        if (array_key_exists('key', $roundTripped)) {
            $key = $roundTripped['key'];
            $value = $roundTripped['value'] ?? null;

            $chips[] = [
                'label' => 'attribute',
                'text' => $value === null ? "{$key} present" : "{$key}: {$value}",
                'href' => route('admin.logs.index', collect($roundTripped)->except(['key', 'value'])->all()),
            ];
        }

        return $chips;
    }

    /**
     * Whether the More-filters disclosure holds a value the primary
     * controls and the applied-state strip's health affordance do not
     * already surface on their own.
     *
     * @param  array<string, string>  $roundTripped
     */
    private function moreFiltersActive(array $roundTripped): bool
    {
        foreach (self::MORE_FILTER_FIELDS as $field) {
            if (array_key_exists($field, $roundTripped)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The quiet "hidden · show" strip affordance a default-hide checkbox
     * gets (health, viewer) and its reverse — the same query with the
     * field toggled, every other filter unchanged.
     *
     * @param  array<string, string>  $roundTripped
     * @return array{hidden: bool, href: string}
     */
    private function toggleAffordance(array $roundTripped, string $field): array
    {
        $hidden = ($roundTripped[$field] ?? null) !== '1';
        $without = collect($roundTripped)->except($field)->all();

        return [
            'hidden' => $hidden,
            'href' => $hidden
                ? route('admin.logs.index', [...$without, $field => '1'])
                : route('admin.logs.index', $without),
        ];
    }
}
