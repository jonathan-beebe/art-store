<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\QuantityDiscount;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\QuantityBreak;

final readonly class UpdateQuantityBreak
{
    public function __invoke(QuantityBreak $break, int $minQty, int $discountBps): QuantityBreak
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating a quantity break', [
            'listing_id' => $break->listing_id,
            'quantity_break_id' => $break->id,
        ], function (Story $story) use ($break, $minQty, $discountBps): QuantityBreak {
            QuantityDiscount::of($minQty, $discountBps);

            $break->update(['min_qty' => $minQty, 'discount_bps' => $discountBps]);

            $story->did('updated the quantity break', [
                'listing_id' => $break->listing_id,
                'quantity_break_id' => $break->id,
                'min_qty' => $break->min_qty,
                'discount_bps' => $break->discount_bps,
            ]);

            return $break;
        });
    }
}
