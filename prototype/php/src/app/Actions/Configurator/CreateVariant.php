<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ComboKey;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Support\Story;

/**
 * One sparse cell: the sole way the walnut table's hand-priced dimension
 * matrix gets its overridden cells without materializing the ones the seller
 * never sells, and the way an axis-free listing's one legacy variant (if a
 * seller wants stock tracking on it) or a single serialized variant (the
 * candlestick lot) comes to exist without a bulk generation step.
 */
final readonly class CreateVariant
{
    /**
     * @param  list<OptionValue>  $optionValues  one per axis; empty for an axis-free listing
     */
    public function __invoke(
        Listing $listing,
        array $optionValues,
        ?int $priceOverrideCents = null,
        ?int $quantity = 1,
        bool $isSerialized = false,
        bool $enabled = true,
    ): Variant {
        return Story::for(StoryEvent::ListingUpdate)->tell('creating a variant', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $optionValues, $priceOverrideCents, $quantity, $isSerialized, $enabled): Variant {
            $comboKey = ComboKey::of(array_map(fn (OptionValue $value): string => $value->id, $optionValues));

            $variant = $listing->variants()->create([
                'combo_key' => $comboKey->value,
                'price_override_cents' => $priceOverrideCents,
                'quantity' => $isSerialized ? null : $quantity,
                'is_serialized' => $isSerialized,
                'enabled' => $enabled,
            ]);

            foreach ($optionValues as $value) {
                $variant->options()->create([
                    'axis_id' => $value->axis_id,
                    'option_value_id' => $value->id,
                ]);
            }

            $story->did('created the variant', [
                'listing_id' => $listing->id,
                'variant_id' => $variant->id,
                'combo_key' => $variant->combo_key,
            ]);

            return $variant;
        });
    }
}
