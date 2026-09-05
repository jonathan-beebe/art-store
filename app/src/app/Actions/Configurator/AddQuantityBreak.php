<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\QuantityDiscount;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\QuantityBreak;

final readonly class AddQuantityBreak
{
    public function __invoke(Listing $listing, int $minQty, int $discountBps): QuantityBreak
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding a quantity break', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $minQty, $discountBps): QuantityBreak {
            // Shape validated the same way the domain resolves it later — a
            // tier that could never apply or discount never lands as a row.
            QuantityDiscount::of($minQty, $discountBps);

            $break = $listing->quantityBreaks()->create([
                'seller_id' => $listing->seller_id,
                'min_qty' => $minQty,
                'discount_bps' => $discountBps,
            ]);

            $story->did('added the quantity break', [
                'listing_id' => $listing->id,
                'quantity_break_id' => $break->id,
                'min_qty' => $break->min_qty,
                'discount_bps' => $break->discount_bps,
            ]);

            return $break;
        });
    }
}
