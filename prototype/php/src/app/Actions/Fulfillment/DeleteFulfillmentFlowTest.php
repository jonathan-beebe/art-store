<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\DomainRuleViolation;
use App\Models\FulfillmentFlow;

it('deletes a flow no listing names and that holds no default role', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller()->id]);

    app(DeleteFulfillmentFlow::class)($flow);

    expect(FulfillmentFlow::find($flow->id))->toBeNull();
});

it('refuses to delete the default flow', function (): void {
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $this->seller()->id]);

    expect(fn () => app(DeleteFulfillmentFlow::class)($flow))->toThrow(DomainRuleViolation::class);
    expect(FulfillmentFlow::find($flow->id))->not->toBeNull();
});

it('refuses to delete a flow a listing names', function (): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $this->listing($seller, ['fulfillment_flow_id' => $flow->id]);

    expect(fn () => app(DeleteFulfillmentFlow::class)($flow))->toThrow(DomainRuleViolation::class);
    expect(FulfillmentFlow::find($flow->id))->not->toBeNull();
});
