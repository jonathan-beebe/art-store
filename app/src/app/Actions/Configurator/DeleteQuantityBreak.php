<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\QuantityBreak;
use App\Support\Story;

final readonly class DeleteQuantityBreak
{
    public function __invoke(QuantityBreak $break): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('deleting a quantity break', [
            'listing_id' => $break->listing_id,
            'quantity_break_id' => $break->id,
        ], function (Story $story) use ($break): void {
            $listingId = $break->listing_id;
            $breakId = $break->id;

            $break->delete();

            $story->did('deleted the quantity break', [
                'listing_id' => $listingId,
                'quantity_break_id' => $breakId,
            ]);
        });
    }
}
