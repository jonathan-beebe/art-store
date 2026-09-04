<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Fulfillment\CompleteFlowStep;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Orders\FulfillmentStatus;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;

it('reads its subtotal, the platform fee, and the seller net as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));
    $fulfillment = $order->fulfillments()->sole();

    expect($fulfillment->subtotal())->toBeMoney(45000)
        ->and($fulfillment->fee())->toBeMoney(4500)
        ->and($fulfillment->net())->toBeMoney(40500);
});

it('adds the fee and the net back up to the subtotal', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 4599]));
    $fulfillment = $order->fulfillments()->sole();

    expect($fulfillment->fee()->add($fulfillment->net())->equals($fulfillment->subtotal()))->toBeTrue();
});

it('reads the ledger entries it produced', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    expect($fulfillment->ledgerEntries()->count())->toBe(2);
});

it('narrows by status and by seller, with a null filter adding no clause', function (): void {
    $sellerA = $this->seller('Blue Kiln Studio');
    $sellerB = $this->seller('Rye Press');
    $fulfillmentA = $this->paidFulfillmentFor($sellerA);
    $fulfillmentB = $this->shippedFulfillmentFor($sellerB);

    expect(Fulfillment::query()->ofStatus(FulfillmentStatus::AwaitingShipment)->pluck('id')->all())->toBe([$fulfillmentA->id])
        ->and(Fulfillment::query()->ofStatus(null)->count())->toBe(2)
        ->and(Fulfillment::query()->ofSeller($sellerB->id)->pluck('id')->all())->toBe([$fulfillmentB->id])
        ->and(Fulfillment::query()->ofSeller(null)->count())->toBe(2);
});

it('counts every status the table holds, in one row each', function (): void {
    $this->paidFulfillmentFor($this->seller());
    $this->deliveredFulfillmentFor($this->seller());

    $counts = [];
    foreach (Fulfillment::query()->countedByStatus()->get() as $row) {
        $counts[$row->status->value] = $row->tally;
    }

    expect($counts)->toEqualCanonicalizing([
        FulfillmentStatus::AwaitingShipment->value => 1,
        FulfillmentStatus::Delivered->value => 1,
    ]);
});

it('counts every status across the whole platform', function (): void {
    $this->paidFulfillmentFor($this->seller());

    expect(Fulfillment::platformCountsByStatus())->toBe([FulfillmentStatus::AwaitingShipment->value => 1]);
});

it('earns the platform fee on a fulfillment still live and forgoes it on one refunded', function (): void {
    $admin = Admin::factory()->create();
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $refunded = $this->deliveredFulfillmentFor($this->seller(), priceCents: 5000);
    app(RefundFulfillment::class)($refunded, $admin, 'Arrived damaged.', $this->moment('2026-08-23 09:00:00'));

    $fees = Fulfillment::platformFees();

    expect($fees->earned->cents)->toBe(1000)
        ->and($fees->refunded->cents)->toBe(500);
});

it('forgoes the fee on a fulfillment a seller declined', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller(), priceCents: 10000);
    app(DeclineFulfillment::class)($fulfillment, 'Out of stock.', $this->moment('2026-08-20 11:00:00'));

    expect(Fulfillment::platformFees()->refunded->cents)->toBe(1000);
});

it('takes the row a transition is judged against for update', function (): void {
    // SQLite has no row lock and its grammar compiles the clause away, so the
    // query is compiled here with the grammar of a database that does have
    // one — what the same read asks for in production.
    $query = Fulfillment::query()->lockedForTransition()->toBase();

    expect((new MySqlGrammar(DB::connection()))->compileSelect($query))->toEndWith('for update');
});

it('re-reads the locked row rather than trusting the instance it was handed', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    Fulfillment::whereKey($fulfillment->id)->update(['status' => FulfillmentStatus::Shipped]);

    expect($fulfillment->status)->toBe(FulfillmentStatus::AwaitingShipment)
        ->and($fulfillment->takeForTransition()->status)->toBe(FulfillmentStatus::Shipped);
});

it('ships by nothing at all when its seller has no flow', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());

    expect($fulfillment->flowInEffect())->toBeNull()
        ->and($fulfillment->flowSteps())->toBe([])
        ->and($fulfillment->progress()->isDone())->toBeTrue()
        ->and($fulfillment->lane())->toBe(FulfillmentLane::ToShip);
});

it('ships by its seller default flow when no listing on it names one', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id, 'name' => 'How I ship']);
    FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);

    expect($fulfillment->flowInEffect()?->id)->toBe($flow->id)
        ->and(array_map(fn (FlowStep $step): string => $step->label, $fulfillment->flowSteps()))->toBe(['Packed']);
});

it('ships by the flow of its first item, in the order Order::items() reads', function (): void {
    $seller = $this->seller('Molly Weasley');
    $earlyFlow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $lateFlow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $earlyListing = $this->listing($seller, ['fulfillment_flow_id' => $earlyFlow->id]);
    $lateListing = $this->listing($seller, ['fulfillment_flow_id' => $lateFlow->id]);

    // Added late listing first, so an unordered read would land on it.
    $order = $this->orderFor($this->verifiedCustomer(), $lateListing, $earlyListing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();

    $order->items()->where('listing_id', $earlyListing->id)->update(['created_at' => $this->moment('2026-08-20 08:00:00')]);
    $order->items()->where('listing_id', $lateListing->id)->update(['created_at' => $this->moment('2026-08-20 09:00:00')]);

    expect($fulfillment->refresh()->flowInEffect()?->id)->toBe($earlyFlow->id);
});

it('reads only step completions as progress, leaving the transition events out', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);

    FulfillmentEvent::factory()->on($fulfillment)->create(['kind' => FulfillmentEventKind::Shipped]);

    expect($fulfillment->load('fulfillmentEvents')->progress()->hasStarted())->toBeFalse();

    FulfillmentEvent::factory()->on($fulfillment)->completing($step)->create();

    expect($fulfillment->load('fulfillmentEvents')->progress()->hasStarted())->toBeTrue();
});

it('keeps a parcel in progress after the seller removes the step they had completed', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $labelStep = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create();
    FulfillmentFlowStep::factory()->of($flow, 1)->create(['label' => 'Packed', 'key' => 'packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);

    app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    expect($fulfillment->refresh()->lane())->toBe(FulfillmentLane::InProgress);

    $labelStep->delete();
    $fulfillment->refresh();

    expect($fulfillment->progress()->hasStarted())->toBeTrue()
        ->and($fulfillment->lane())->toBe(FulfillmentLane::InProgress)
        ->and($fulfillment->progress()->completed)->toBe([])
        ->and($fulfillment->progress()->next()?->label)->toBe('Packed');
});

it('keeps the words of a step the seller removed on the row that recorded it', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $labelStep = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create(['label' => 'Label printed']);
    $fulfillment = $this->paidFulfillmentFor($seller);

    app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));
    $labelStep->delete();

    $event = FulfillmentEvent::where('fulfillment_id', $fulfillment->id)->sole();

    expect($event->fulfillment_flow_step_id)->toBeNull()
        ->and($event->stepLabel())->toBe('Label printed');
});

it('selects each lane the way the loaded flow reads it', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);

    $toShip = $this->paidFulfillmentFor($seller);
    $started = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($started, $step, null, null, $this->moment('2026-08-21 09:00:00'));
    $shipped = $this->shippedFulfillmentFor($seller);
    $delivered = $this->deliveredFulfillmentFor($seller);

    $inLane = fn (LaneFilter $filter): array => Fulfillment::query()
        ->whereBelongsTo($seller)
        ->inLane($filter)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($inLane(LaneFilter::ToShip))->toBe([$toShip->id])
        ->and($inLane(LaneFilter::InProgress))->toEqualCanonicalizing([$started->id, $shipped->id])
        ->and($inLane(LaneFilter::Done))->toBe([$delivered->id])
        ->and($inLane(LaneFilter::All))->toHaveCount(4);
});

it('agrees with the lane each fulfillment reads off its own flow', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);

    $started = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($started, $step, null, null, $this->moment('2026-08-21 09:00:00'));
    $this->paidFulfillmentFor($seller);
    $this->shippedFulfillmentFor($seller);
    $this->deliveredFulfillmentFor($seller);
    app(DeclineFulfillment::class)($this->paidFulfillmentFor($seller), 'The kiln cracked it.', $this->moment('2026-08-22 09:00:00'));
    app(RefundFulfillment::class)($this->shippedFulfillmentFor($seller), $this->admin(), 'The parcel never arrived.', $this->moment('2026-08-23 09:00:00'));

    foreach (Fulfillment::query()->whereBelongsTo($seller)->get() as $fulfillment) {
        $selected = Fulfillment::query()->inLane(LaneFilter::of($fulfillment->lane()))->pluck('id')->all();

        expect($selected)->toContain($fulfillment->id);
    }
});

it('counts the two facts a lane is read from, one row per pair', function (): void {
    $seller = $this->seller('Ginny Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);

    $started = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($started, $step, null, null, $this->moment('2026-08-21 09:00:00'));
    $this->paidFulfillmentFor($seller);
    $this->paidFulfillmentFor($seller);

    $counted = [];
    foreach (Fulfillment::query()->whereBelongsTo($seller)->countedByLane()->get() as $row) {
        $counted[FulfillmentLane::forStarted($row->status, $row->started)->value] = $row->tally;
    }

    expect($counted)->toEqualCanonicalizing([
        FulfillmentLane::ToShip->value => 2,
        FulfillmentLane::InProgress->value => 1,
    ]);
});

it('names the seller lines on the order as one phrase', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor(
        $this->verifiedCustomer(),
        $this->listing($seller, ['title' => 'Harbour at Dusk']),
        $this->listing($seller, ['title' => 'Kiln Study']),
    );
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    expect($order->fulfillments()->with('order.items')->sole()->itemLabel())->toBe('Harbour at Dusk +1 more');
});

it('says a parcel carrying none of the sellers lines has no items', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Rye Press'));
    $fulfillment->forceFill(['seller_id' => $this->seller('Blue Kiln Studio')->id])->save();

    expect(Fulfillment::query()->with('order.items')->findOrFail($fulfillment->id)->itemLabel())->toBe('no items');
});
