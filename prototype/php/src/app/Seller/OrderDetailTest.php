<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\CompleteFlowStep;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Escrow\LedgerEntryType;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;

$flowWithTwoSteps = function (Seller $seller): FulfillmentFlowStep {
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id, 'name' => 'How I ship']);
    $label = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create(['label' => 'Label printed']);
    FulfillmentFlowStep::factory()->of($flow, 1)->create(['label' => 'Packed', 'key' => 'packed']);

    return $label;
};

it('reads a delivered parcel as the sentence the order page carries', function (): void {
    $seller = $this->seller();
    $delivered = $this->deliveredFulfillmentFor($seller, priceCents: 10000, deliveredAt: $this->moment('2026-08-28 11:00:00'));

    $facts = app(OrderDetail::class)->facts($delivered, $seller, $this->moment('2026-09-04 09:00:00'));

    expect($facts->state->line())->toBe('Delivered Aug 28 · $90.00 released to your balance');
});

it('reads a parcel still in the studio as the clock the buyer is watching', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);

    $facts = app(OrderDetail::class)->facts($fulfillment, $seller, $this->moment('2026-08-22 09:00:00'));

    expect($facts->state->line())->toBe('Placed 2 days ago · ship by Aug 23');
});

it('reads the last completed step into the state of a parcel that has started', function () use ($flowWithTwoSteps): void {
    $seller = $this->seller('Arthur Weasley');
    $step = $flowWithTwoSteps($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $facts = app(OrderDetail::class)->facts($fulfillment->refresh(), $seller, $this->moment('2026-08-21 12:00:00'));

    expect($facts->state->line())->toBe('Label printed 3 hours ago · waiting for the parcel to leave');
});

it('says where the money for a parcel stands', function (): void {
    $seller = $this->seller();
    $detail = app(OrderDetail::class);

    $held = $detail->facts($this->paidFulfillmentFor($seller), $seller, $this->moment('2026-08-22 09:00:00'));
    $released = $detail->facts($this->deliveredFulfillmentFor($seller), $seller, $this->moment('2026-08-22 09:00:00'));

    expect($held->escrow)->toBe(LedgerEntryType::Held)
        ->and($released->escrow)->toBe(LedgerEntryType::Released);
});

it('says nothing about the money on a parcel whose ledger is empty', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    $fulfillment->ledgerEntries()->delete();

    $facts = app(OrderDetail::class)->facts($fulfillment->refresh(), $seller, $this->moment('2026-08-22 09:00:00'));

    expect($facts->escrow)->toBeNull();
});

it('reads the day the money went back as a declined parcels resting day', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 45000);
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $facts = app(OrderDetail::class)->facts($fulfillment->refresh(), $seller, $this->moment('2026-08-22 09:00:00'));

    expect($facts->state->line())->toBe('Declined Aug 21 · $450.00 returned to the buyer');
});

it('names who marked each step behind the parcel and when', function () use ($flowWithTwoSteps): void {
    $seller = $this->seller('Molly Weasley');
    $step = $flowWithTwoSteps($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $facts = app(OrderDetail::class)->facts($fulfillment->refresh(), $seller, $this->moment('2026-08-22 09:00:00'));
    $completion = $facts->completed[$step->id];

    expect($facts->completed)->toHaveCount(1)
        ->and($completion->step->label)->toBe('Label printed')
        ->and($completion->actor)->toBe('Molly Weasley')
        ->and($completion->line())->toBe('Done by Molly Weasley · Aug 21');
});

it('leaves a step nobody has marked out of the completions', function () use ($flowWithTwoSteps): void {
    $seller = $this->seller('Luna Lovegood');
    $flowWithTwoSteps($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);

    $facts = app(OrderDetail::class)->facts($fulfillment, $seller, $this->moment('2026-08-22 09:00:00'));

    expect($facts->completed)->toBe([])
        ->and($facts->steps)->toHaveCount(2)
        ->and($facts->progress->next()?->label)->toBe('Label printed');
});

it('leaves a completion whose step the seller removed out of the panel', function () use ($flowWithTwoSteps): void {
    $seller = $this->seller('Ginny Weasley');
    $step = $flowWithTwoSteps($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));
    $step->delete();

    $facts = app(OrderDetail::class)->facts($fulfillment->refresh(), $seller, $this->moment('2026-08-22 09:00:00'));

    expect($facts->completed)->toBe([])
        ->and($facts->progress->hasStarted())->toBeTrue();
});

it('names the flow the parcel ships by and the card the buyer paid with', function () use ($flowWithTwoSteps): void {
    $seller = $this->seller('Arthur Weasley');
    $flowWithTwoSteps($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);

    $facts = app(OrderDetail::class)->facts($fulfillment, $seller, $this->moment('2026-08-22 09:00:00'));

    expect($facts->flowName)->toBe('How I ship')
        ->and($facts->cardLastFour)->toBe('4242')
        ->and($facts->paymentStatus)->toBe('Approved');
});

it('reads one order page in the same number of queries whatever its order holds', function () use ($flowWithTwoSteps): void {
    $seller = $this->seller('Molly Weasley');
    $label = $flowWithTwoSteps($seller);

    $queriesFor = function (int $itemCount) use ($seller, $label): int {
        $order = $this->orderFor(
            $this->verifiedCustomer(),
            ...array_map(fn (): Listing => $this->listing($seller), range(1, $itemCount)),
        );
        $order = app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
        $fulfillment = $order->fulfillments()->sole();
        app(CompleteFlowStep::class)($fulfillment, $label, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $facts = app(OrderDetail::class)->facts($fulfillment->refresh(), $seller, $this->moment('2026-08-22 09:00:00'));

        expect($facts->state->line())->toStartWith('Label printed')
            ->and($facts->completed)->toHaveCount(1);

        return $queries;
    };

    $withOne = $queriesFor(1);
    $withTwo = $queriesFor(2);

    expect($withTwo)->toBe($withOne);
});
