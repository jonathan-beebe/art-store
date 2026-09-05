<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Configurator\ListingPriceSync;
use App\Domain\Configurator\ConfiguratorDeletionGuard;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\OptionValue;
use LogicException;

final readonly class DeleteOptionValue
{
    public function __invoke(OptionValue $value): void
    {
        $axis = $value->axis ?? throw new LogicException('An option value always belongs to an axis.');

        Story::for(StoryEvent::ListingUpdate)->tell('deleting an option value', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $value->axis_id,
            'option_value_id' => $value->id,
        ], function (Story $story) use ($value, $axis): void {
            ConfiguratorDeletionGuard::forOptionValue($value->variantOptions()->exists());

            $listingId = $axis->listing_id;
            $axisId = $value->axis_id;
            $valueId = $value->id;
            $listing = $axis->listing ?? throw new LogicException('An option axis always belongs to a listing.');

            $value->delete();

            ListingPriceSync::sync($listing);

            $story->did('deleted the option value', [
                'listing_id' => $listingId,
                'axis_id' => $axisId,
                'option_value_id' => $valueId,
            ]);
        });
    }
}
