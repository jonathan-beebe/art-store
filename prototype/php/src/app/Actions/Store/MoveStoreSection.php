<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StoreSectionMove;
use App\Models\StoreSection;
use Illuminate\Support\Facades\DB;

/**
 * Swaps one section with its neighbor one place earlier or later. The swap
 * passes through a sentinel position because `store_sections` is unique on
 * `(store_profile_id, position)` and SQLite enforces that immediately
 * rather than at commit.
 */
final readonly class MoveStoreSection
{
    private const int SENTINEL_POSITION = -1;

    public function __invoke(StoreSection $section, StoreSectionMove $direction): void
    {
        $neighbor = $this->neighbor($section, $direction);

        if (! $neighbor instanceof StoreSection) {
            return;
        }

        DB::transaction(function () use ($section, $neighbor): void {
            $sectionPosition = $section->position;
            $neighborPosition = $neighbor->position;

            $section->update(['position' => self::SENTINEL_POSITION]);
            $neighbor->update(['position' => $sectionPosition]);
            $section->update(['position' => $neighborPosition]);
        });
    }

    private function neighbor(StoreSection $section, StoreSectionMove $direction): ?StoreSection
    {
        $siblings = StoreSection::where('store_profile_id', $section->store_profile_id);

        return $direction === StoreSectionMove::Up
            ? $siblings->where('position', '<', $section->position)->orderByDesc('position')->first()
            : $siblings->where('position', '>', $section->position)->orderBy('position')->first();
    }
}
