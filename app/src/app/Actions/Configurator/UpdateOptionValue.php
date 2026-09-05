<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Configurator\ListingPriceSync;
use App\Domain\Configurator\PricingMode;
use App\Domain\DomainRuleViolation;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\OptionValue;
use App\Models\PropertyValue;
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
        ?int $priceCents = null,
    ): OptionValue {
        $axis = $value->axis ?? throw new LogicException('An option value always belongs to an axis.');

        return Story::for(StoryEvent::ListingUpdate)->tell('updating an option value', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $value->axis_id,
        ], function (Story $story) use ($value, $axis, $label, $surchargeCents, $isDefault, $position, $propertyValue, $priceCents): OptionValue {
            $isStandalone = $axis->pricing_mode === PricingMode::Standalone;

            if ($isStandalone && $priceCents === null) {
                throw new DomainRuleViolation('Every option on this choice needs its own price.');
            }

            $value->update([
                'property_value_id' => $propertyValue?->id,
                'label' => $label,
                'surcharge_cents' => $isStandalone ? 0 : $surchargeCents,
                'price_cents' => $isStandalone ? $priceCents : null,
                'is_default' => $isDefault,
                'position' => $position,
            ]);

            ListingPriceSync::sync($axis->listing ?? throw new LogicException('An option axis always belongs to a listing.'));

            $story->did('updated the option value', [
                'listing_id' => $axis->listing_id,
                'axis_id' => $value->axis_id,
                'option_value_id' => $value->id,
                'label' => $value->label,
                'surcharge_cents' => $value->surcharge_cents,
                'price_cents' => $value->price_cents,
            ]);

            return $value;
        });
    }
}
