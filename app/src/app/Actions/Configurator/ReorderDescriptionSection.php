<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\DescriptionSectionMove;
use App\Logging\StoryEvent;
use App\Models\DescriptionSection;
use App\Support\Story;
use Illuminate\Support\Facades\DB;

/**
 * Swaps one section with its neighbor one place earlier or later — the whole
 * of "reorder" for a list lacking drag-and-drop, JavaScript off. The swap
 * passes through a sentinel position first, since `description_sections` is
 * unique on `(listing_id, position)` and SQLite enforces that constraint
 * immediately, before the transaction commits.
 */
final readonly class ReorderDescriptionSection
{
    private const int SENTINEL_POSITION = -1;

    public function __invoke(DescriptionSection $section, DescriptionSectionMove $direction): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('reordering a description section', [
            'listing_id' => $section->listing_id,
            'description_section_id' => $section->id,
            'direction' => $direction->value,
        ], function (Story $story) use ($section, $direction): void {
            $neighbor = $direction === DescriptionSectionMove::Up
                ? DescriptionSection::where('listing_id', $section->listing_id)->where('position', '<', $section->position)->orderByDesc('position')->first()
                : DescriptionSection::where('listing_id', $section->listing_id)->where('position', '>', $section->position)->orderBy('position')->first();

            if (! $neighbor instanceof DescriptionSection) {
                $story->did('nothing to reorder', [
                    'listing_id' => $section->listing_id,
                    'description_section_id' => $section->id,
                ]);

                return;
            }

            DB::transaction(function () use ($section, $neighbor): void {
                $sectionPosition = $section->position;
                $neighborPosition = $neighbor->position;

                $section->update(['position' => self::SENTINEL_POSITION]);
                $neighbor->update(['position' => $sectionPosition]);
                $section->update(['position' => $neighborPosition]);
            });

            $story->did('reordered the description section', [
                'listing_id' => $section->listing_id,
                'description_section_id' => $section->id,
                'position' => $section->position,
            ]);
        });
    }
}
