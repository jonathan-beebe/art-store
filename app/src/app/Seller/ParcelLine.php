<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Listings\PlaceholderImage;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * The seller's own lines on a parcel, read as one phrase and one picture —
 * the scan line every order row, feed sentence, and thread rail about a
 * parcel carries. Every caller eager-loads `order.items`; a caller that has
 * not is refused by the lazy-loading guard.
 */
final readonly class ParcelLine
{
    private function __construct() {} // @codeCoverageIgnore

    public static function label(Fulfillment $fulfillment): string
    {
        $items = self::itemsFor($fulfillment);
        $first = $items->first();

        if (! $first instanceof OrderItem) {
            return 'no items';
        }

        $label = $first->quantity > 1 ? "{$first->title} ×{$first->quantity}" : $first->title;
        $rest = $items->count() - 1;

        return $rest > 0 ? "{$label} +{$rest} more" : $label;
    }

    /**
     * A picture for the parcel's first line, scoped to the seller the same
     * way {@see self::label()} is — the listing's own cover, or a
     * placeholder titled from the label when the line's listing is gone.
     */
    public static function imageUrl(Fulfillment $fulfillment): string
    {
        $listing = self::itemsFor($fulfillment)->first()?->listing;

        return $listing?->imageUrl() ?? PlaceholderImage::dataUri(self::label($fulfillment));
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private static function itemsFor(Fulfillment $fulfillment): Collection
    {
        return $fulfillment->order->items
            ->where('seller_id', $fulfillment->seller_id)
            ->values();
    }
}
