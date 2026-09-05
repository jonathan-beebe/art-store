<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Configurator\UpdateOptionValue;
use App\Analytics\Analytics;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\UnitState;
use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\BlockedLine;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderPlacementRefused;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\UnavailableReason;
use App\Models\CustomerBlock;
use App\Models\FulfillmentFlow;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Variant;
use DomainException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

it('turns the cart into an order the customer can pay for', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->subtotal_cents)->toBe(45000)
        ->and($order->total_cents)->toBe(45000)
        ->and($order->finalized_at)->toBeNull()
        ->and($order->placed_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
});

it('places an order that waits for verification for an unverified customer', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->status)->toBe(OrderStatus::PendingVerification);
});

it('copies the shipping address onto the order', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->shipping_name)->toBe('Ada Lovelace')
        ->and($order->shipping_line1)->toBe('12 Analytical Way')
        ->and($order->shipping_line2)->toBeNull()
        ->and($order->shipping_postal_code)->toBe('EC1A 1BB');
});

it('snapshots the title and price of every item', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dusk', 'price_cents' => 45000]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    $item = $order->items()->sole();

    expect($item->title)->toBe('Harbour at Dusk')
        ->and($item->unit_price_cents)->toBe(45000)
        ->and($item->seller_id)->toBe($listing->seller_id);
});

it('splits the order into one fulfillment per seller', function (): void {
    $customer = $this->verifiedCustomer();
    $first = $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]);
    $second = $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]);
    $cart = $this->cartFor($customer);
    $addToCart = app(AddToCart::class);
    $addToCart($cart, $first, 1, $this->moment('2026-08-20 08:00:00'));
    $addToCart($cart, $second, 1, $this->moment('2026-08-20 08:00:00'));

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->subtotal_cents)->toBe(55000);
    expect(
        $order->fulfillments()->orderBy('seller_id')->get()
            ->map(fn ($fulfillment) => [
                $fulfillment->seller_id,
                $fulfillment->subtotal_cents,
                $fulfillment->fee_cents,
                $fulfillment->net_cents,
            ])->all(),
    )->toBe([
        [$first->seller_id, 45000, 4500, 40500],
        [$second->seller_id, 10000, 1000, 9000],
    ]);
});

it('snapshots the listings own flow onto its fulfillment', function (): void {
    $customer = $this->verifiedCustomer();
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $listing = $this->listing($seller, ['fulfillment_flow_id' => $flow->id]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->fulfillments()->sole()->fulfillment_flow_id)->toBe($flow->id);
});

it('snapshots the sellers default flow when the listing names none', function (): void {
    $customer = $this->verifiedCustomer();
    $seller = $this->seller();
    $default = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $listing = $this->listing($seller);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->fulfillments()->sole()->fulfillment_flow_id)->toBe($default->id);
});

it('leaves the snapshot null for a seller with no flow at all', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 10000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->fulfillments()->sole()->fulfillment_flow_id)->toBeNull();
});

it('starts every fulfillment awaiting shipment', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->fulfillments()->sole()->status)->toBe(FulfillmentStatus::AwaitingShipment);
});

it('takes the stock the order claims', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 3]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 08:00:00'));

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    $listing->refresh();

    expect($listing->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('DSGN-003 leaves a made-to-order listings quantity null after the order claims stock', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => null]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 5, $this->moment('2026-08-20 08:00:00'));

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    $listing->refresh();

    expect($listing->quantity)->toBeNull()
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('marks a listing sold when the order claims the last of it', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 1]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    $listing->refresh();

    expect($listing->quantity)->toBe(0)
        ->and($listing->status)->toBe(ListingStatus::Sold);
});

it('empties the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($cart->items()->count())->toBe(0);
});

it('refuses an empty cart', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
})->throws(DomainException::class);

it('refuses a listing that left the storefront while it sat in the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'price_cents' => 45000]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
    $listing->update(['status' => ListingStatus::Archived]);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(OrderPlacementRefused::class, '“Harbour at Dawn” is no longer available to buy.')
        ->and(Order::count())->toBe(0)
        ->and($cart->items()->count())->toBe(1)
        ->and($listing->refresh()->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::Archived);
});

it('judges the cart against the rows it locked, not what the caller loaded before', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 45000, 'quantity' => 1]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
    // What the page the shopper submitted from had read: one left, placeable.
    $cart->load('items.listing');
    Listing::whereKey($listing->id)->update(['quantity' => 0, 'status' => ListingStatus::Sold]);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(OrderPlacementRefused::class, '“Winter Elm” is no longer available to buy.')
        ->and(Order::count())->toBe(0);
});

it('refuses a blocked customer', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);
    CustomerBlock::factory()->create(['customer_id' => $customer->id, 'reason' => 'Chargeback fraud.']);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(DomainRuleViolation::class, 'Chargeback fraud.')
        ->and(Order::count())->toBe(0);
});

it('refuses a listing whose last unit sold to someone else', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 45000, 'quantity' => 1]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
    $this->orderFor($this->verifiedCustomer(), $listing);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(OrderPlacementRefused::class, '“Winter Elm” is no longer available to buy.')
        ->and(Order::count())->toBe(1);
});

it('refuses a line asking for more than remains in stock', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 45000, 'quantity' => 2]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 08:00:00'));
    $this->orderFor($this->verifiedCustomer(), $listing->refresh());

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(OrderPlacementRefused::class)
        ->and(Order::count())->toBe(1)
        ->and($listing->refresh()->quantity)->toBe(1);
});

it('refuses every blocked line at once, not just the first', function (): void {
    $customer = $this->verifiedCustomer();
    $offSale = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $soldOut = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 24500, 'quantity' => 1]);
    $cart = $this->cartFor($customer);
    $addToCart = app(AddToCart::class);
    $addToCart($cart, $offSale, 1, $this->moment('2026-08-20 08:00:00'));
    $addToCart($cart, $soldOut, 1, $this->moment('2026-08-20 08:00:00'));
    $offSale->update(['status' => ListingStatus::Archived]);
    $this->orderFor($this->verifiedCustomer(), $soldOut->refresh());

    try {
        app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        throw new RuntimeException('Expected placement to be refused.');
    } catch (OrderPlacementRefused $refusal) {
        expect(array_map(
            fn (BlockedLine $line): array => [$line->title, $line->reason],
            $refusal->blocked,
        ))->toBe([
            ['Harbour at Dawn', UnavailableReason::OffSale],
            ['Winter Elm', UnavailableReason::SoldOut],
        ]);
    }

    expect(Order::count())->toBe(1);
});

it('freezes a configured lines price, configuration, and answers at placement', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Engraved Signet Ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);
    $variant = Variant::whereHas('options', fn ($query) => $query->where('option_value_id', $roseGold->id))->sole();
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Engraving Text', addOnPriceCents: 500);

    $cart = $this->cartFor($customer);
    app(AddToCart::class)(
        $cart,
        $listing,
        1,
        $this->moment('2026-08-20 08:00:00'),
        listingHasVariants: true,
        variant: $variant,
        configuration: [['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $roseGold->id, 'optionValueLabel' => 'Rose Gold']],
        answers: [$text->id => ['prompt' => 'Engraving Text', 'answer' => 'ADA', 'raw' => 'ADA']],
        fingerprintAnswers: [$text->id => 'ADA'],
    );

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    $item = $order->items()->sole();

    expect($item->variant_id)->toBe($variant->id)
        ->and($item->unit_id)->toBeNull()
        ->and($item->configuration_json)->toBe([
            ['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $roseGold->id, 'optionValueLabel' => 'Rose Gold'],
        ])
        ->and($item->answers_json)->toBe([$text->id => ['prompt' => 'Engraving Text', 'answer' => 'ADA', 'raw' => 'ADA']])
        ->and($item->price_breakdown_json)->toBe([
            ['label' => 'Base price', 'cents' => 12000],
            ['label' => 'Rose Gold', 'cents' => 800],
            ['label' => 'Engraving Text', 'cents' => 500],
        ])
        ->and($item->lineTotal())->toBeMoney(13300);
});

it('claims a serialized lines unit and decrements a non-serialized variants quantity inside placement', function (): void {
    $customer = $this->verifiedCustomer();
    $tee = $this->listing($this->seller(), ['title' => 'Line Art Cat Tee']);
    $axis = app(CreateOptionAxis::class)($tee, 'Size');
    app(AddOptionValue::class)($axis, 'M', 0, isDefault: true);
    app(GenerateVariants::class)($tee);
    $teeVariant = $tee->variants()->sole();
    $teeVariant->update(['quantity' => 3]);

    $candlesticks = $this->listing($this->seller(), ['title' => 'Vintage Brass Candlesticks']);
    $variant = app(CreateVariant::class)($candlesticks, [], isSerialized: true);
    $unit = app(AddUnit::class)($variant, '#1');

    $cart = $this->cartFor($customer);
    $addToCart = app(AddToCart::class);
    $addToCart($cart, $tee, 2, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $teeVariant);
    $addToCart($cart, $candlesticks, 1, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant, unitId: $unit->id);

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($teeVariant->refresh()->quantity)->toBe(1)
        ->and($unit->refresh()->state)->toBe(UnitState::Sold)
        ->and($tee->refresh()->quantity)->toBe(1)
        ->and($tee->status)->toBe(ListingStatus::ForSale)
        ->and($candlesticks->refresh()->quantity)->toBe(1)
        ->and($candlesticks->status)->toBe(ListingStatus::ForSale);
});

it('blocks a configured line whose variant sold out while it sat in the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Line Art Cat Tee']);
    $axis = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($axis, 'M', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = $listing->variants()->sole();
    $variant->update(['quantity' => 1]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant);
    $variant->update(['quantity' => 0]);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(OrderPlacementRefused::class, '“Line Art Cat Tee” is no longer available to buy.')
        ->and(Order::count())->toBe(0);
});

it('blocks a serialized line whose unit sold to someone else while it sat in the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Vintage Brass Candlesticks']);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $unit = app(AddUnit::class)($variant, '#1');
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant, unitId: $unit->id);
    $unit->update(['state' => UnitState::Sold]);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(OrderPlacementRefused::class, '“Vintage Brass Candlesticks” is no longer available to buy.')
        ->and(Order::count())->toBe(0)
        ->and($unit->refresh()->state)->toBe(UnitState::Sold);
});

it('leaves the placed orders price and configuration unchanged after the seller edits the axis surcharge', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Engraved Signet Ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);
    $variant = Variant::whereHas('options', fn ($query) => $query->where('option_value_id', $roseGold->id))->sole();

    $cart = $this->cartFor($customer);
    app(AddToCart::class)(
        $cart,
        $listing,
        1,
        $this->moment('2026-08-20 08:00:00'),
        listingHasVariants: true,
        variant: $variant,
        configuration: [['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $roseGold->id, 'optionValueLabel' => 'Rose Gold']],
    );
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    $item = $order->items()->sole();
    $frozenBreakdown = $item->price_breakdown_json;
    $frozenConfiguration = $item->configuration_json;
    $frozenTotal = $item->lineTotal();

    // The seller raises Rose Gold's surcharge well after the sale.
    app(UpdateOptionValue::class)($roseGold->refresh(), 'Rose Gold', 5000, false, 0, null);

    $item->refresh();

    expect($item->price_breakdown_json)->toBe($frozenBreakdown)
        ->and($item->configuration_json)->toBe($frozenConfiguration)
        ->and($item->lineTotal())->toBeMoney($frozenTotal->cents)
        ->and($roseGold->refresh()->surcharge_cents)->toBe(5000);
});

it('records an order.place event carrying the order\'s listings', function (): void {
    $customer = $this->verifiedCustomer();
    $listingA = $this->listing($this->seller(), ['price_cents' => 24500]);
    $listingB = $this->listing($this->seller(), ['price_cents' => 12000]);
    $cart = $this->cartFor($customer);
    $addToCart = app(AddToCart::class);
    $addToCart($cart, $listingA, 1, $this->moment('2026-08-20 08:00:00'));
    $addToCart($cart, $listingB, 1, $this->moment('2026-08-20 08:00:00'));
    $now = $this->moment('2026-08-20 09:00:00');

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $now);
    app(Analytics::class)->flush();

    $event = DB::connection('analytics')->table('analytics_events')->where('name', 'order.place')->sole();
    /** @var string $eventData */
    $eventData = $event->data;
    /** @var array<string, mixed> $data */
    $data = json_decode($eventData, true);

    expect($event->subject_type)->toBe('order')
        ->and($event->subject_id)->toBe($order->id)
        ->and($event->actor_id)->toBe($customer->id)
        ->and($event->occurred_at)->toBe('2026-08-20 09:00:00')
        ->and($data['listing_ids'])->toEqualCanonicalizing([$listingA->id, $listingB->id]);
});

it('records no order.place event when placement is refused', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'price_cents' => 45000]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
    $listing->update(['status' => ListingStatus::Archived]);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(OrderPlacementRefused::class);

    app(Analytics::class)->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'order.place')->exists())->toBeFalse();
});

it('never queries analytics_events on the commerce connection and buffers the order.place event only once the placement transaction has committed', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);
    $analytics = app(Analytics::class);
    $analytics->flush(); // drops the cart_add event buffered above, so pending() below counts only what PlaceOrder itself records

    $commerceQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$commerceQueries): void {
        if ($query->connectionName === 'sqlite' && str_contains($query->sql, 'analytics_events')) {
            $commerceQueries[] = $query->sql;
        }
    });

    $pendingAtCommit = null;
    Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$pendingAtCommit, $analytics): void {
        if ($event->connectionName === 'sqlite') {
            $pendingAtCommit = $analytics->pending();
        }
    });

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($commerceQueries)->toBe([])
        ->and($pendingAtCommit)->toBe(0)
        ->and($analytics->pending())->toBe(1);
});
