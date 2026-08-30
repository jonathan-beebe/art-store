<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The bounded window every admin list pane's cells read through (DSGN-006
 * follow-up): at most SIZE rows off an already-filtered, already-ordered
 * query, without losing the row a show route has open when it sorts outside
 * that window, and without hiding how many rows exist beyond it.
 */
final readonly class ListPaneWindow
{
    public const int SIZE = 50;

    /**
     * @param  Collection<int, Model>  $items
     */
    private function __construct(
        public Collection $items,
        public int $total,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  already filtered and ordered; read via a count and a capped fetch, neither mutated on the caller's copy
     * @param  TModel|null  $mustInclude  the item the current page has open, guaranteed a place in `items` with a single extra fetch when the window would otherwise have left it out
     */
    public static function of(Builder $query, ?Model $mustInclude = null): self
    {
        $total = (clone $query)->count();
        $items = (clone $query)->limit(self::SIZE)->get();

        if ($mustInclude !== null && ! $items->contains($mustInclude->getKeyName(), '=', $mustInclude->getKey())) {
            $missing = (clone $query)->whereKey($mustInclude->getKey())->first();

            if ($missing !== null) {
                $items->prepend($missing);
            }
        }

        return new self($items, $total);
    }

    /**
     * Whether the window left rows out of `items` — the signal a list
     * pane's footer uses to decide whether it has anything to say at all.
     */
    public function hasMore(): bool
    {
        return $this->total > $this->items->count();
    }
}
