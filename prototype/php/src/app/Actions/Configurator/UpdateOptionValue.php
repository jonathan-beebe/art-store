<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\OptionValue;
use App\Models\PropertyValue;
use App\Support\Story;
use LogicException;

final readonly class UpdateOptionValue
{
    public function __invoke(
        OptionValue $value,
        string $label,
        int $surchargeCents,
        bool $isDefault,
        int $position,
        ?PropertyValue $propertyValue,
    ): OptionValue {
        $axis = $value->axis ?? throw new LogicException('An option value always belongs to an axis.');

        return Story::for(StoryEvent::ListingUpdate)->tell('updating an option value', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $value->axis_id,
        ], function (Story $story) use ($value, $axis, $label, $surchargeCents, $isDefault, $position, $propertyValue): OptionValue {
            $value->update([
                'property_value_id' => $propertyValue?->id,
                'label' => $label,
                'surcharge_cents' => $surchargeCents,
                'is_default' => $isDefault,
                'position' => $position,
            ]);

            $story->did('updated the option value', [
                'listing_id' => $axis->listing_id,
                'axis_id' => $value->axis_id,
                'option_value_id' => $value->id,
                'label' => $value->label,
                'surcharge_cents' => $value->surcharge_cents,
            ]);

            return $value;
        });
    }
}
