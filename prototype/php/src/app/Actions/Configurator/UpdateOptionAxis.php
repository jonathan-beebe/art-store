<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\OptionAxis;
use App\Models\Property;
use App\Support\Story;

final readonly class UpdateOptionAxis
{
    public function __invoke(OptionAxis $axis, string $name, ?Property $property, int $position): OptionAxis
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating an option axis', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $axis->id,
        ], function (Story $story) use ($axis, $name, $property, $position): OptionAxis {
            $axis->update([
                'property_id' => $property?->id,
                'name' => $name,
                'position' => $position,
            ]);

            $story->did('updated the option axis', [
                'listing_id' => $axis->listing_id,
                'axis_id' => $axis->id,
                'name' => $axis->name,
            ]);

            return $axis;
        });
    }
}
