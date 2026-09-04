<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Fulfillment\DefaultFlow;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\Seller;
use RuntimeException;

beforeEach(function (): void {
    $this->seed();
});

it('gives every seller a default flow named DefaultFlow::NAME with the two default steps in order', function (): void {
    foreach (Seller::all() as $seller) {
        $flow = FulfillmentFlow::where('seller_id', $seller->id)->where('is_default', true)->sole();

        expect($flow->name)->toBe(DefaultFlow::NAME);

        $labels = $flow->load('steps')->steps->pluck('label')->all();
        expect($labels)->toBe(['Label printed', 'Packed']);
    }
});

it('gives a shipped fulfillment a step_completed event for the label step, timed before it shipped, carrying a carrier', function (): void {
    $fulfillment = Fulfillment::whereNotNull('shipped_at')->firstOrFail();
    $shippedAt = $fulfillment->shipped_at ?? throw new RuntimeException('The query selected a shipped fulfillment.');

    $event = FulfillmentEvent::where('fulfillment_id', $fulfillment->id)
        ->where('kind', FulfillmentEventKind::StepCompleted)
        ->sole();

    expect($event->carrier)->not->toBeNull()
        ->and($event->occurred_at->format(DATE_ATOM))->toBeLessThan($shippedAt->format(DATE_ATOM));
});

it('leaves a fulfillment that never shipped with no step event', function (): void {
    $fulfillment = Fulfillment::whereNull('shipped_at')->firstOrFail();

    expect(FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->where('kind', FulfillmentEventKind::StepCompleted)->count())->toBe(0);
});
