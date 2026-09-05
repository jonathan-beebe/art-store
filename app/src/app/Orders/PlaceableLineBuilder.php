<?php

declare(strict_types=1);

namespace App\Orders;

use App\Domain\Configurator\UnitState;
use App\Domain\Orders\PlaceableLine;
use App\Models\CartItem;
use App\Models\OrderItem;
use LogicException;

/**
 * Folds a cart or order line's listing (and, when it is configured, its
 * variant and unit) into the {@see PlaceableLine} the pure
 * {@see \App\Domain\Orders\OrderPlacementPlan} judges — shared by
 * {@see \App\Models\Cart::placementPlan()} and
 * {@see \App\Models\Order::placementPlan()} so the mapping lives in one
 * place. The caller is responsible for eager-loading `listing`, and
 * `variant`/`unit` for a configured line, before calling this.
 */
final class PlaceableLineBuilder
{
    private function __construct() {} // @codeCoverageIgnore

    public static function for(CartItem|OrderItem $item): PlaceableLine
    {
        $listing = $item->listing;
        // An order item's own `title` is the snapshot frozen at placement,
        // so a later rename does not change what a retry's refusal names.
        // A cart item has no snapshot yet; it reads the listing's title
        // live, same as today.
        $title = $item instanceof OrderItem ? $item->title : $listing->title;

        if (! $item->hasVariant()) {
            return new PlaceableLine(
                listingId: $item->listing_id,
                title: $title,
                status: $listing->status,
                availableQuantity: $listing->quantity,
                quantity: $item->quantity,
                hasActiveRemoval: $listing->hasActiveRemoval(),
                lineId: $item->id,
            );
        }

        $variant = $item->variant ?? throw new LogicException('A configured line always resolves to a variant.');

        return new PlaceableLine(
            listingId: $item->listing_id,
            title: $title,
            status: $listing->status,
            availableQuantity: $listing->quantity,
            quantity: $item->quantity,
            hasActiveRemoval: $listing->hasActiveRemoval(),
            lineId: $item->id,
            configured: true,
            variantEnabled: $variant->enabled,
            serialized: $variant->is_serialized,
            unitAvailable: $item->unit_id === null || $item->unit?->state === UnitState::Available,
            variantRemainingQuantity: $variant->quantity,
        );
    }
}
