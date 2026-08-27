<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\PropertyValue;
use App\Support\Story;

final readonly class AddOptionValue
{
    public function __invoke(
        OptionAxis $axis,
        string $label,
        int $surchargeCents = 0,
        bool $isDefault = false,
        int $position = 0,
        ?PropertyValue $propertyValue = null,
    ): OptionValue {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding an option value', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $axis->id,
        ], function (Story $story) use ($axis, $label, $surchargeCents, $isDefault, $position, $propertyValue): OptionValue {
            $value = $axis->optionValues()->create([
                'property_value_id' => $propertyValue?->id,
                'label' => $label,
                'surcharge_cents' => $surchargeCents,
                'is_default' => $isDefault,
                'position' => $position,
            ]);

            $story->did('added the option value', [
                'listing_id' => $axis->listing_id,
                'axis_id' => $axis->id,
                'option_value_id' => $value->id,
                'label' => $value->label,
                'surcharge_cents' => $value->surcharge_cents,
            ]);

            return $value;
        });
    }
}
