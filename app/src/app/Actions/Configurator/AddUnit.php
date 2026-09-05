<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Unit;
use App\Models\Variant;

final readonly class AddUnit
{
    /**
     * @param  array<string, int|float|string|bool>|null  $specs  measured, per-unit facts (height_mm, condition, …)
     */
    public function __invoke(
        Variant $variant,
        string $label,
        ?string $conditionNote = null,
        ?array $specs = null,
        ?int $priceOverrideCents = null,
    ): Unit {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding a unit', [
            'listing_id' => $variant->listing_id,
            'variant_id' => $variant->id,
        ], function (Story $story) use ($variant, $label, $conditionNote, $specs, $priceOverrideCents): Unit {
            $unit = $variant->units()->create([
                'seller_id' => $variant->seller_id,
                'label' => $label,
                'condition_note' => $conditionNote,
                'specs_json' => $specs,
                'price_override_cents' => $priceOverrideCents,
            ]);

            $story->did('added the unit', [
                'listing_id' => $variant->listing_id,
                'variant_id' => $variant->id,
                'unit_id' => $unit->id,
                'label' => $unit->label,
            ]);

            return $unit;
        });
    }
}
