<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\DescriptionSection;
use App\Support\Story;

final readonly class DeleteDescriptionSection
{
    public function __invoke(DescriptionSection $section): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('deleting a description section', [
            'listing_id' => $section->listing_id,
            'description_section_id' => $section->id,
        ], function (Story $story) use ($section): void {
            $listingId = $section->listing_id;
            $sectionId = $section->id;

            $section->delete();

            $story->did('deleted the description section', [
                'listing_id' => $listingId,
                'description_section_id' => $sectionId,
            ]);
        });
    }
}
