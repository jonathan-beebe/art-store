<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\Variant;
use App\Support\Story;

/**
 * The per-cell edit the variant grid offers on a row already generated or
 * created sparse: the walnut table's hand price on one dimension, a
 * discontinued combination disabled, or a cell turned serialized.
 */
final readonly class UpdateVariant
{
    public function __invoke(
        Variant $variant,
        ?int $priceOverrideCents,
        ?int $quantity,
        bool $isSerialized,
        bool $enabled,
        ?string $sku = null,
    ): Variant {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating a variant', [
            'listing_id' => $variant->listing_id,
            'variant_id' => $variant->id,
        ], function (Story $story) use ($variant, $priceOverrideCents, $quantity, $isSerialized, $enabled, $sku): Variant {
            $variant->update([
                'sku' => $sku,
                'price_override_cents' => $priceOverrideCents,
                'quantity' => $isSerialized ? null : $quantity,
                'is_serialized' => $isSerialized,
                'enabled' => $enabled,
            ]);

            $story->did('updated the variant', [
                'listing_id' => $variant->listing_id,
                'variant_id' => $variant->id,
                'price_override_cents' => $variant->price_override_cents,
                'enabled' => $variant->enabled,
            ]);

            return $variant;
        });
    }
}
