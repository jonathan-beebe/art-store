<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\Modifier;
use App\Support\Story;

final readonly class DeleteModifier
{
    public function __invoke(Modifier $modifier): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('deleting a modifier', [
            'listing_id' => $modifier->listing_id,
            'modifier_id' => $modifier->id,
        ], function (Story $story) use ($modifier): void {
            $listingId = $modifier->listing_id;
            $modifierId = $modifier->id;

            $modifier->delete();

            $story->did('deleted the modifier', [
                'listing_id' => $listingId,
                'modifier_id' => $modifierId,
            ]);
        });
    }
}
