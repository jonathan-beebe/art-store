<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Fulfillment\CompleteFlowStep;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Orders\FulfillmentStatus;
use App\Seller\FulfillmentFlowReader;
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

    $reader = app(FulfillmentFlowReader::class);

    foreach (Fulfillment::query()->whereBelongsTo($seller)->get() as $fulfillment) {
        $fulfillment->load([
            'order.items.listing.fulfillmentFlow.steps',
            'seller.defaultFulfillmentFlow.steps',
            'fulfillmentEvents',
        ]);

        $selected = Fulfillment::query()->inLane(LaneFilter::of($fulfillment->lane($reader->progress($fulfillment))))->pluck('id')->all();

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
