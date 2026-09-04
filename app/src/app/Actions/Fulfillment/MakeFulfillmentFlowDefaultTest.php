<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Models\FulfillmentFlow;

it('hands the default role to the named flow, taking it from the old one', function (): void {
    $seller = $this->seller();
    $old = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $new = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);

    app(MakeFulfillmentFlowDefault::class)($new);

    expect($old->refresh()->is_default)->toBeFalse()
        ->and($new->refresh()->is_default)->toBeTrue();
});

it('leaves every other sellers default alone', function (): void {
    $other = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $this->seller('Other Studio')->id]);
    $seller = $this->seller();
    $new = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);

    app(MakeFulfillmentFlowDefault::class)($new);

    expect($other->refresh()->is_default)->toBeTrue();
});

it('leaves an already-default flow as the one default', function (): void {
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $this->seller()->id]);

    app(MakeFulfillmentFlowDefault::class)($flow);

    expect($flow->refresh()->is_default)->toBeTrue()
        ->and(FulfillmentFlow::where('seller_id', $flow->seller_id)->where('is_default', true)->count())->toBe(1);
});
