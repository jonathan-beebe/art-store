<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\DomainRuleViolation;
use App\Logging\StoryEvent;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\PropertyValue;
use App\Support\Configurator\ListingPriceSync;
use App\Support\Story;
use LogicException;

final readonly class AddOptionValue
{
    public function __invoke(
        OptionAxis $axis,
        string $label,
        int $surchargeCents = 0,
        bool $isDefault = false,
        int $position = 0,
        ?PropertyValue $propertyValue = null,
        ?int $priceCents = null,
    ): OptionValue {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding an option value', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $axis->id,
        ], function (Story $story) use ($axis, $label, $surchargeCents, $isDefault, $position, $propertyValue, $priceCents): OptionValue {
            $isStandalone = $axis->pricing_mode === PricingMode::Standalone;

            if ($isStandalone && $priceCents === null) {
                throw new DomainRuleViolation('Every option on this choice needs its own price.');
            }

            $value = $axis->optionValues()->create([
                'property_value_id' => $propertyValue?->id,
                'label' => $label,
                'surcharge_cents' => $isStandalone ? 0 : $surchargeCents,
                'price_cents' => $isStandalone ? $priceCents : null,
                'is_default' => $isDefault,
                'position' => $position,
            ]);

            ListingPriceSync::sync($axis->listing ?? throw new LogicException('An option axis always belongs to a listing.'));

            $story->did('added the option value', [
                'listing_id' => $axis->listing_id,
                'axis_id' => $axis->id,
                'option_value_id' => $value->id,
                'label' => $value->label,
                'surcharge_cents' => $value->surcharge_cents,
                'price_cents' => $value->price_cents,
            ]);

            return $value;
        });
    }
}
