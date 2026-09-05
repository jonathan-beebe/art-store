<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Support\Story;

final readonly class AddModifierOption
{
    public function __invoke(Modifier $modifier, string $label, int $addOnPriceCents = 0, int $position = 0): ModifierOption
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding a modifier option', [
            'listing_id' => $modifier->listing_id,
            'modifier_id' => $modifier->id,
        ], function (Story $story) use ($modifier, $label, $addOnPriceCents, $position): ModifierOption {
            $option = $modifier->options()->create([
                'seller_id' => $modifier->seller_id,
                'label' => $label,
                'add_on_price_cents' => $addOnPriceCents,
                'position' => $position,
            ]);

            $story->did('added the modifier option', [
                'listing_id' => $modifier->listing_id,
                'modifier_id' => $modifier->id,
                'modifier_option_id' => $option->id,
                'label' => $option->label,
            ]);

            return $option;
        });
    }
}
