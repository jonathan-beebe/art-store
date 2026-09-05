<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ConfiguratorDeletionGuard;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\CartItem;
use App\Models\OrderItem;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

final readonly class DeleteVariant
{
    public function __invoke(Variant $variant): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('deleting a variant', [
            'listing_id' => $variant->listing_id,
            'variant_id' => $variant->id,
        ], function (Story $story) use ($variant): void {
            DB::transaction(function () use ($story, $variant): void {
                ConfiguratorDeletionGuard::forVariant(
                    CartItem::where('variant_id', $variant->id)->exists()
                        || OrderItem::where('variant_id', $variant->id)->awaitingShipment()->exists(),
                );

                $variant->delete();

                $story->did('deleted the variant', [
                    'listing_id' => $variant->listing_id,
                    'variant_id' => $variant->id,
                ]);
            });
        });
    }
}
