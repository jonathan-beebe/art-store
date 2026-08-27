<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\ModifierOption;
use App\Support\Story;
use LogicException;

final readonly class UpdateModifierOption
{
    public function __invoke(ModifierOption $option, string $label, int $addOnPriceCents, int $position): ModifierOption
    {
        $modifier = $option->modifier ?? throw new LogicException('A modifier option always belongs to a modifier.');

        return Story::for(StoryEvent::ListingUpdate)->tell('updating a modifier option', [
            'listing_id' => $modifier->listing_id,
            'modifier_id' => $option->modifier_id,
            'modifier_option_id' => $option->id,
        ], function (Story $story) use ($option, $modifier, $label, $addOnPriceCents, $position): ModifierOption {
            $option->update([
                'label' => $label,
                'add_on_price_cents' => $addOnPriceCents,
                'position' => $position,
            ]);

            $story->did('updated the modifier option', [
                'listing_id' => $modifier->listing_id,
                'modifier_id' => $option->modifier_id,
                'modifier_option_id' => $option->id,
                'label' => $option->label,
            ]);

            return $option;
        });
    }
}
