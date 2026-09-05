<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\UnitState;
use App\Logging\StoryEvent;
use App\Models\Unit;
use App\Support\Story;
use LogicException;

final readonly class UpdateUnit
{
    /**
     * @param  array<string, int|float|string|bool>|null  $specs
     */
    public function __invoke(
        Unit $unit,
        string $label,
        UnitState $state,
        ?string $conditionNote,
        ?array $specs,
        ?int $priceOverrideCents,
    ): Unit {
        $variant = $unit->variant ?? throw new LogicException('A unit always belongs to a variant.');

        return Story::for(StoryEvent::ListingUpdate)->tell('updating a unit', [
            'listing_id' => $variant->listing_id,
            'variant_id' => $unit->variant_id,
            'unit_id' => $unit->id,
        ], function (Story $story) use ($unit, $variant, $label, $state, $conditionNote, $specs, $priceOverrideCents): Unit {
            $unit->update([
                'label' => $label,
                'state' => $state,
                'condition_note' => $conditionNote,
                'specs_json' => $specs,
                'price_override_cents' => $priceOverrideCents,
            ]);

            $story->did('updated the unit', [
                'listing_id' => $variant->listing_id,
                'variant_id' => $unit->variant_id,
                'unit_id' => $unit->id,
                'state' => $unit->state->value,
            ]);

            return $unit;
        });
    }
}
