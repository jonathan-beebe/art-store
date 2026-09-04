<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use Illuminate\Database\QueryException;
use RuntimeException;

it('mints a prefixed id', function (): void {
    expect(FulfillmentEvent::factory()->create()->id)->toStartWith('fev_');
});

it('casts its kind, actor type, and occurred_at', function (): void {
    $event = FulfillmentEvent::factory()->create([
        'kind' => FulfillmentEventKind::Shipped,
        'actor_type' => ActorType::Seller,
        'occurred_at' => $this->moment('2026-08-20 09:00:00'),
    ]);

    expect($event->kind)->toBe(FulfillmentEventKind::Shipped)
        ->and($event->actor_type)->toBe(ActorType::Seller)
        ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
});

it('orders events oldest first, breaking a tied occurred_at by id', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    $tiedMoment = $this->moment('2026-08-20 09:00:00');
    $first = FulfillmentEvent::factory()->on($fulfillment)->create(['occurred_at' => $tiedMoment]);
    $second = FulfillmentEvent::factory()->on($fulfillment)->create(['occurred_at' => $tiedMoment]);
    $earliest = FulfillmentEvent::factory()->on($fulfillment)->create(['occurred_at' => $this->moment('2026-08-19 09:00:00')]);

    expect(FulfillmentEvent::query()->inOrder()->pluck('id')->all())->toBe([$earliest->id, $first->id, $second->id]);
});

it('resolves its fulfillment, seller, and step relations, with no step on a transition event', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create();
    $completion = FulfillmentEvent::factory()->on($fulfillment)->completing($step)->create();
    $transition = FulfillmentEvent::factory()->on($fulfillment)->create(['kind' => FulfillmentEventKind::Shipped]);
    $completion->load(['fulfillment', 'seller', 'fulfillmentFlowStep']);
    $transition->load(['fulfillment', 'seller', 'fulfillmentFlowStep']);

    expect($completion->fulfillment->is($fulfillment))->toBeTrue()
        ->and($completion->seller->is($seller))->toBeTrue()
        ->and($completion->fulfillmentFlowStep?->is($step))->toBeTrue()
        ->and($transition->fulfillmentFlowStep)->toBeNull();
});

it('rejects a second step-completed event for the same fulfillment and step', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create();
    FulfillmentEvent::factory()->on($fulfillment)->completing($step)->create();

    expect(fn () => FulfillmentEvent::factory()->on($fulfillment)->completing($step)->create())
        ->toThrow(QueryException::class);
});

it('permits many events with no step on one fulfillment', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    FulfillmentEvent::factory()->on($fulfillment)->create(['kind' => FulfillmentEventKind::Shipped]);
    FulfillmentEvent::factory()->on($fulfillment)->create(['kind' => FulfillmentEventKind::Delivered]);

    expect(FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->count())->toBe(2);
});

it('reads back the step words a completion recorded', function (): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Kiln cooled']);
    $fulfillment = $this->paidFulfillmentFor($seller);

    $event = FulfillmentEvent::factory()->on($fulfillment)->completing($step)->create();

    expect($event->stepLabel())->toBe('Kiln cooled');
});

it('refuses to read step words off a row that kept none', function (): void {
    $event = new FulfillmentEvent(['kind' => FulfillmentEventKind::StepCompleted]);

    expect(fn (): string => $event->stepLabel())->toThrow(RuntimeException::class);
});
