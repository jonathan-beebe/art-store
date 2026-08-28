<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\DomainRuleViolation;
use App\Models\CartItem;
use App\Models\OptionAxis;
use App\Models\OptionValue;
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

it('refuses to delete a variant an order still holds', function (): void {
    $variant = Variant::factory()->create();
    OrderItem::factory()->create(['listing_id' => $variant->listing_id, 'variant_id' => $variant->id]);

    app(DeleteVariant::class)($variant);
})->throws(DomainRuleViolation::class);
