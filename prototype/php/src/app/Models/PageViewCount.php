<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Analytics\PageViewWeek;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\PageViewCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * One row per (site, path_pattern, day): a request's route pattern rolled up
 * at response time rather than logged per hit, so the table grows with
 * routes and days rather than with traffic. The table lives in the
 * analytics store (config/database.php), a SQLite file of its own next to
 * the commerce database.
 * {@see \App\Analytics\Analytics::flush()} is the only writer, through an
 * upsert on this table's unique triple.
 */
#[Fillable(['site', 'path_pattern', 'day', 'count'])]
class PageViewCount extends Model
{
    /** @use HasFactory<PageViewCountFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    protected $connection = 'analytics';

    public static function idPrefix(): string
    {
        return 'pvc';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function inWeek(Builder $query, PageViewWeek $week): void
    {
        $query->whereBetween('day', [$week->firstDay, $week->lastDay]);
    }

    public static function totalForWeek(PageViewWeek $week): int
    {
        return (int) self::query()->inWeek($week)->sum('count');
    }

    /**
     * Every day inside the window that saw traffic, newest first.
     *
     * @return list<array{day: string, count: int}>
     */
    public static function totalsByDay(PageViewWeek $week): array
    {
        return array_values(self::query()
            ->inWeek($week)
            ->select('day')
            ->selectRaw('sum(count) as count')
            ->groupBy('day')
            ->orderByDesc('day')
            ->get()
            ->map(fn (self $row): array => ['day' => $row->day, 'count' => (int) $row->count])
            ->all());
    }

    /**
     * Every route pattern that has ever seen traffic, busiest first.
     *
     * @return list<array{site: string, pathPattern: string, count: int}>
     */
    public static function totalsByPattern(): array
    {
        return array_values(self::query()
            ->select('site', 'path_pattern')
            ->selectRaw('sum(count) as count')
            ->groupBy('site', 'path_pattern')
            ->orderByDesc('count')
            ->orderBy('site')
            ->orderBy('path_pattern')
            ->get()
            ->map(fn (self $row): array => [
                'site' => $row->site,
                'pathPattern' => $row->path_pattern,
                'count' => (int) $row->count,
            ])
            ->all());
    }
}
