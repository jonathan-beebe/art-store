<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\ModifierOption;
use LogicException;

final readonly class DeleteModifierOption
{
    public function __invoke(ModifierOption $option): void
    {
        $modifier = $option->modifier ?? throw new LogicException('A modifier option always belongs to a modifier.');

        Story::for(StoryEvent::ListingUpdate)->tell('deleting a modifier option', [
            'listing_id' => $modifier->listing_id,
            'modifier_id' => $option->modifier_id,
            'modifier_option_id' => $option->id,
        ], function (Story $story) use ($option, $modifier): void {
            $listingId = $modifier->listing_id;
            $modifierId = $option->modifier_id;
            $optionId = $option->id;

            $option->delete();

            $story->did('deleted the modifier option', [
                'listing_id' => $listingId,
                'modifier_id' => $modifierId,
                'modifier_option_id' => $optionId,
            ]);
        });
    }
}
