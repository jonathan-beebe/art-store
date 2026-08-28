<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\DomainRuleViolation;
use App\Models\CartItem;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Variant;
use App\Models\VariantOption;

it('deletes a variant no cart or order references', function (): void {
    $axis = OptionAxis::factory()->create();
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $axis->listing_id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    app(DeleteVariant::class)($variant);

    expect(Variant::find($variant->id))->toBeNull();
});

it('refuses to delete a variant a cart still holds', function (): void {
    $variant = Variant::factory()->create();
    CartItem::factory()->create(['listing_id' => $variant->listing_id, 'variant_id' => $variant->id]);

    app(DeleteVariant::class)($variant);
})->throws(DomainRuleViolation::class);

it('refuses to delete a variant an order still awaiting shipment holds', function (): void {
    $listing = Listing::factory()->create();
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    $order = Order::factory()->paid()->create();
    Fulfillment::factory()->awaitingShipment()->create(['order_id' => $order->id, 'seller_id' => $listing->seller_id]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'listing_id' => $listing->id,
        'seller_id' => $listing->seller_id,
        'variant_id' => $variant->id,
    ]);

    app(DeleteVariant::class)($variant);
})->throws(DomainRuleViolation::class);

it('deletes a variant only a shipped order references', function (): void {
    $listing = Listing::factory()->create();
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    $order = Order::factory()->paid()->create();
    Fulfillment::factory()->shipped()->create(['order_id' => $order->id, 'seller_id' => $listing->seller_id]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'listing_id' => $listing->id,
        'seller_id' => $listing->seller_id,
        'variant_id' => $variant->id,
        'title' => 'A frozen title',
    ]);

    app(DeleteVariant::class)($variant);

    $frozen = OrderItem::findOrFail($item->id);

    expect(Variant::find($variant->id))->toBeNull()
        // The order item's own snapshot survives the variant's deletion —
        // only the live reference nulls out.
        ->and($frozen->variant_id)->toBeNull()
        ->and($frozen->title)->toBe('A frozen title');
});

it('deletes a variant only a delivered order references', function (): void {
    $listing = Listing::factory()->create();
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    $order = Order::factory()->paid()->create();
    Fulfillment::factory()->delivered()->create(['order_id' => $order->id, 'seller_id' => $listing->seller_id]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'listing_id' => $listing->id,
        'seller_id' => $listing->seller_id,
        'variant_id' => $variant->id,
    ]);

    app(DeleteVariant::class)($variant);

    expect(Variant::find($variant->id))->toBeNull();
});
