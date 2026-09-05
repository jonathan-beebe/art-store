<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Configurator\ListingPriceSync;
use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\PricingModeChangeGuard;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\OptionAxis;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class UpdateOptionAxis
{
    public function __invoke(OptionAxis $axis, string $name, ?Property $property, int $position, PricingMode $pricingMode): OptionAxis
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating an option axis', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $axis->id,
        ], function (Story $story) use ($axis, $name, $property, $position, $pricingMode): OptionAxis {
            return DB::transaction(function () use ($story, $axis, $name, $property, $position, $pricingMode): OptionAxis {
                PricingModeChangeGuard::forAxis($axis->pricing_mode !== $pricingMode, $axis->optionValues()->exists());

                $axis->update([
                    'property_id' => $property?->id,
                    'name' => $name,
                    'position' => $position,
                    'pricing_mode' => $pricingMode,
                ]);

                ListingPriceSync::sync($axis->listing ?? throw new LogicException('An option axis always belongs to a listing.'));

                $story->did('updated the option axis', [
                    'listing_id' => $axis->listing_id,
                    'axis_id' => $axis->id,
                    'name' => $axis->name,
                    'pricing_mode' => $pricingMode->value,
                ]);

                return $axis;
            });
        });
    }
}
