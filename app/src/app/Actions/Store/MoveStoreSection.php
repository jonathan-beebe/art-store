<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StoreSectionMove;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Illuminate\Support\Facades\DB;

/**
 * Swaps one section with its neighbor one place earlier or later. The swap
 * passes through a sentinel position: `store_sections` is unique on
 * `(store_profile_id, position)` and SQLite enforces that as each statement
 * runs, so the row being moved parks somewhere free while its neighbor
 * takes its place. `StoreSection::MAX_PER_PROFILE` caps how many sections a
 * store holds at once; the position values keep climbing on their own —
 * {@see AddStoreSection}'s `max(position) + 1` climbs with every section
 * ever added. The sentinel sits inside the unsigned range the column
 * holds, well clear of where that climb reaches in practice.
 */
final readonly class MoveStoreSection
{
    private const int SENTINEL_POSITION = 9999;

    public function __invoke(StoreSection $section, StoreSectionMove $direction): void
    {
        DB::transaction(function () use ($section, $direction): void {
            // Locks the same row AddStoreSection locks, serializing the two
            // writers: each one reads the position the other already
            // committed.
            StoreProfile::query()->whereKey($section->store_profile_id)->lockForUpdate()->sole();

            $locked = $section->newQuery()->whereKey($section->getKey())->lockForUpdate()->sole();
            $neighbor = $this->neighbor($locked, $direction);

            if (! $neighbor instanceof StoreSection) {
                return;
            }

            $sectionPosition = $locked->position;
            $neighborPosition = $neighbor->position;

            $locked->update(['position' => self::SENTINEL_POSITION]);
            $neighbor->update(['position' => $sectionPosition]);
            $locked->update(['position' => $neighborPosition]);
        });
    }

    /**
     * The section's neighbor, held for the rest of the transaction so a
     * second move reads the position this one is about to write.
     */
    private function neighbor(StoreSection $section, StoreSectionMove $direction): ?StoreSection
    {
        $siblings = StoreSection::where('store_profile_id', $section->store_profile_id)->lockForUpdate();

        return $direction === StoreSectionMove::Up
            ? $siblings->where('position', '<', $section->position)->orderByDesc('position')->first()
            : $siblings->where('position', '>', $section->position)->orderBy('position')->first();
    }
}
