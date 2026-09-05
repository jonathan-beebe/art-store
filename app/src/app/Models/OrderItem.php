<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\PriceBreakdown;
use App\Domain\Configurator\PriceBreakdownLine;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Concerns\HasPrefixedUlid;
use App\Models\Concerns\MapsToPlaceableLine;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Override;

/**
 * @property-read Order $order
 * @property-read Listing $listing
 * @property-read Seller $seller
 * @property-read Variant|null $variant
 * @property-read Unit|null $unit
 * @property list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>|null $configuration_json
 * @property array<string, array{prompt: string, answer: string, raw: string}>|null $answers_json
 * @property list<array{label: string, cents: int}>|null $price_breakdown_json
 */
#[Fillable([
    'order_id', 'customer_id', 'listing_id', 'seller_id', 'title', 'unit_price_cents', 'quantity',
    'variant_id', 'unit_id', 'configuration_json', 'answers_json', 'price_breakdown_json',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    use HasPrefixedUlid;
    use MapsToPlaceableLine;

    public static function idPrefix(): string
    {
        return 'oit';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'configuration_json' => 'array',
            'answers_json' => 'array',
            'price_breakdown_json' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function hasVariant(): bool
    {
        return $this->variant_id !== null;
    }

    /**
     * Whether placement froze a price breakdown for this line — false for a
     * legacy line placed before the snapshot existed, which carries none.
     */
    public function hasPricedBreakdown(): bool
    {
        return $this->price_breakdown_json !== null;
    }

    /**
     * Narrows to items whose seller could still decline the parcel they ride
     * in on, the one fulfillment transition that reads a variant back onto
     * the shelf ({@see \App\Orders\StockMovement::release()}). Every
     * later fulfillment status settles the item on its own frozen columns.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingShipment(Builder $query): void
    {
        $query->whereExists(function (QueryBuilder $query): void {
            $query->selectRaw('1')
                ->from('fulfillments')
                ->whereColumn('fulfillments.order_id', 'order_items.order_id')
                ->whereColumn('fulfillments.seller_id', 'order_items.seller_id')
                ->where('fulfillments.status', FulfillmentStatus::AwaitingShipment);
        });
    }

    public function unitPrice(): Money
    {
        return Money::fromCents($this->unit_price_cents);
    }

    /**
     * The itemized breakdown frozen at placement — never re-derived from the
     * listing's current configurator rows, unlike {@see CartItem::currentBreakdown()},
     * which reads them live. Empty for a legacy line, which carries none.
     */
    public function priceBreakdown(): PriceBreakdown
    {
        return PriceBreakdown::of(array_map(
            fn (array $line): PriceBreakdownLine => PriceBreakdownLine::of($line['label'], Money::fromCents($line['cents'])),
            $this->price_breakdown_json ?? [],
        ));
    }

    /**
     * A line that froze a breakdown totals that breakdown: surcharges,
     * answer add-ons, and the quantity discount already folded in. Once a
     * breakdown exists, `unit_price_cents * quantity` is only a
     * representative per-unit figure (see `PlaceOrder`).
     */
    public function lineTotal(): Money
    {
        return $this->hasPricedBreakdown() ? $this->priceBreakdown()->total() : $this->unitPrice()->multiply($this->quantity);
    }

    /**
     * An order item's own `title` is the snapshot frozen at placement, so a
     * later rename leaves what a retry's refusal names unchanged.
     */
    protected function placeableLineTitle(): string
    {
        return $this->title;
    }
}
