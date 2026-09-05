<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\DescriptionSection;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

final readonly class AddDescriptionSection
{
    /**
     * @param  array<int|string, mixed>|null  $bodyJson
     */
    public function __invoke(
        Listing $listing,
        int $position,
        DescriptionSectionKind $kind,
        ?string $title = null,
        ?string $bodyMd = null,
        ?array $bodyJson = null,
    ): DescriptionSection {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding a description section', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $position, $kind, $title, $bodyMd, $bodyJson): DescriptionSection {
            return DB::transaction(function () use ($story, $listing, $position, $kind, $title, $bodyMd, $bodyJson): DescriptionSection {
                $section = $listing->descriptionSections()->create([
                    'seller_id' => $listing->seller_id,
                    'position' => $position,
                    'kind' => $kind,
                    'title' => $title,
                    'body_md' => $bodyMd,
                    'body_json' => $bodyJson,
                ]);

                $story->did('added the description section', [
                    'listing_id' => $listing->id,
                    'description_section_id' => $section->id,
                    'kind' => $section->kind->value,
                    'position' => $section->position,
                ]);

                return $section;
            });
        });
    }
}
