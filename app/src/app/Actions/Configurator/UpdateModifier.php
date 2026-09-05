<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ModifierKind;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Modifier;
use Illuminate\Support\Facades\DB;

final readonly class UpdateModifier
{
    public function __invoke(
        Modifier $modifier,
        ModifierKind $kind,
        string $prompt,
        ?string $instructions,
        bool $required,
        int $position,
        int $addOnPriceCents,
        ?int $charLimit,
        ?string $unit,
        ?float $minValue,
        ?float $maxValue,
        ?int $rateCentsPerUnit,
    ): Modifier {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating a modifier', [
            'listing_id' => $modifier->listing_id,
            'modifier_id' => $modifier->id,
        ], function (Story $story) use (
            $modifier, $kind, $prompt, $instructions, $required, $position,
            $addOnPriceCents, $charLimit, $unit, $minValue, $maxValue, $rateCentsPerUnit,
        ): Modifier {
            return DB::transaction(function () use ($story, $modifier, $kind, $prompt, $instructions, $required, $position, $addOnPriceCents, $charLimit, $unit, $minValue, $maxValue, $rateCentsPerUnit): Modifier {
                $modifier->update([
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

                $story->did('updated the modifier', [
                    'listing_id' => $modifier->listing_id,
                    'modifier_id' => $modifier->id,
                    'kind' => $modifier->kind->value,
                    'prompt' => $modifier->prompt,
                ]);

                return $modifier;
            });
        });
    }
}
