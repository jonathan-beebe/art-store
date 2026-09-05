<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\OptionValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The variant grid's bulk action: every variant selecting one axis value
 * (Size = Large, say) enabled or disabled together in one action — the
 * walnut table's 136-combination grid culled to what actually sells
 * without a click per cell.
 */
final readonly class SetVariantsEnabledByOptionValue
{
    public function __invoke(Listing $listing, OptionValue $optionValue, bool $enabled): int
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('setting variants enabled by option value', [
            'listing_id' => $listing->id,
            'option_value_id' => $optionValue->id,
            'enabled' => $enabled,
        ], function (Story $story) use ($listing, $optionValue, $enabled): int {
            return DB::transaction(function () use ($story, $listing, $optionValue, $enabled): int {
                $count = $listing->variants()
                    ->whereHas('options', fn (Builder $options): Builder => $options->where('option_value_id', $optionValue->id))
                    ->update(['enabled' => $enabled]);

                $story->did('set variants enabled by option value', [
                    'listing_id' => $listing->id,
                    'option_value_id' => $optionValue->id,
                    'enabled' => $enabled,
                    'updated_count' => $count,
                ]);

                return $count;
            });
        });
    }
}
