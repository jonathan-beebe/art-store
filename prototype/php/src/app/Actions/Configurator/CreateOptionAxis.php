<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\Property;
use App\Support\Story;

final readonly class CreateOptionAxis
{
    public function __invoke(Listing $listing, string $name, ?Property $property = null, int $position = 0): OptionAxis
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding an option axis', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $name, $property, $position): OptionAxis {
            $axis = $listing->optionAxes()->create([
                'property_id' => $property?->id,
                'name' => $name,
                'position' => $position,
            ]);

            $story->did('added the option axis', [
                'listing_id' => $listing->id,
                'axis_id' => $axis->id,
                'name' => $axis->name,
            ]);

            return $axis;
        });
    }
}
