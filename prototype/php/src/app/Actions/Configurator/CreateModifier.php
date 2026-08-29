<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ModifierKind;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\Modifier;
use App\Support\Story;

final readonly class CreateModifier
{
    public function __invoke(
        Listing $listing,
        ModifierKind $kind,
        string $prompt,
        ?string $instructions = null,
        bool $required = false,
        int $position = 0,
        int $addOnPriceCents = 0,
        ?int $charLimit = null,
        ?string $unit = null,
        ?float $minValue = null,
        ?float $maxValue = null,
        ?int $rateCentsPerUnit = null,
    ): Modifier {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding a modifier', [
            'listing_id' => $listing->id,
        ], function (Story $story) use (
            $listing, $kind, $prompt, $instructions, $required, $position,
            $addOnPriceCents, $charLimit, $unit, $minValue, $maxValue, $rateCentsPerUnit,
        ): Modifier {
            $modifier = $listing->modifiers()->create([
                'kind' => $kind,
                'prompt' => $prompt,
                'instructions' => $instructions,
                'required' => $required,
                'position' => $position,
                'add_on_price_cents' => $addOnPriceCents,
                'char_limit' => $charLimit,
                'unit' => $unit,
                'min_value' => $minValue,
                'max_value' => $maxValue,
                'rate_cents_per_unit' => $rateCentsPerUnit,
            ]);

            $story->did('added the modifier', [
                'listing_id' => $listing->id,
                'modifier_id' => $modifier->id,
                'kind' => $modifier->kind->value,
                'prompt' => $modifier->prompt,
            ]);

            return $modifier;
        });
    }
}
