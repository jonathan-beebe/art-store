<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\DescriptionSection;

final readonly class UpdateDescriptionSection
{
    /**
     * @param  array<int|string, mixed>|null  $bodyJson
     */
    public function __invoke(
        DescriptionSection $section,
        DescriptionSectionKind $kind,
        ?string $title,
        ?string $bodyMd,
        ?array $bodyJson,
    ): DescriptionSection {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating a description section', [
            'listing_id' => $section->listing_id,
            'description_section_id' => $section->id,
        ], function (Story $story) use ($section, $kind, $title, $bodyMd, $bodyJson): DescriptionSection {
            $section->update([
                'kind' => $kind,
                'title' => $title,
                'body_md' => $bodyMd,
                'body_json' => $bodyJson,
            ]);

            $story->did('updated the description section', [
                'listing_id' => $section->listing_id,
                'description_section_id' => $section->id,
                'kind' => $section->kind->value,
            ]);

            return $section;
        });
    }
}
