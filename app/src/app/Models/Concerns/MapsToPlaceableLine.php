<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Domain\Configurator\UnitState;
use App\Domain\Orders\PlaceableLine;
use App\Models\Listing;
use App\Models\Unit;
use App\Models\Variant;
use LogicException;

/**
 * Folds a cart or order line's listing (and, when it is configured, its
 * variant and unit) into the {@see PlaceableLine} the pure
 * {@see \App\Domain\Orders\OrderPlacementPlan} judges. `CartItem` and
 * `OrderItem` share the fold and differ in one thing: where the title
 * comes from. The caller eager-loads `listing`, and `variant` and `unit`
 * for a configured line, before asking.
 *
 * @property string $id
 * @property string $listing_id
 * @property string|null $unit_id
 * @property int $quantity
 * @property-read Listing $listing
 * @property-read Variant|null $variant
 * @property-read Unit|null $unit
 */
trait MapsToPlaceableLine
{
    abstract public function hasVariant(): bool;

    abstract protected function placeableLineTitle(): string;

    public function toPlaceableLine(): PlaceableLine
    {
        $listing = $this->listing;

        if (! $this->hasVariant()) {
            return new PlaceableLine(
                listingId: $this->listing_id,
                title: $this->placeableLineTitle(),
                status: $listing->status,
                availableQuantity: $listing->quantity,
                quantity: $this->quantity,
                hasActiveRemoval: $listing->hasActiveRemoval(),
                lineId: $this->id,
            );
        }

        $variant = $this->variant ?? throw new LogicException('A configured line always resolves to a variant.');

        return new PlaceableLine(
            listingId: $this->listing_id,
            title: $this->placeableLineTitle(),
            status: $listing->status,
            availableQuantity: $listing->quantity,
            quantity: $this->quantity,
            hasActiveRemoval: $listing->hasActiveRemoval(),
            lineId: $this->id,
            configured: true,
            variantEnabled: $variant->enabled,
            serialized: $variant->is_serialized,
            unitAvailable: $this->unit_id === null || $this->unit?->state === UnitState::Available,
            variantRemainingQuantity: $variant->quantity,
        );
    }
}
