<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\CompleteFlowStep;
use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Messaging\ConversationKind;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;

$flowWithOneStep = function (Seller $seller, string $label = 'Label printed'): FulfillmentFlowStep {
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);

    return FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create(['label' => $label]);
};

it('counts the lanes that ask for work and leaves the archives uncounted', function () use ($flowWithOneStep): void {
    $seller = $this->seller('Molly Weasley');
    $step = $flowWithOneStep($seller);
    $started = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($started, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));
    $this->paidFulfillmentFor($seller);
    $this->paidFulfillmentFor($seller);
    // A shipped parcel is In progress off its status alone, so the tab's
    // number is the sum of two rows of the grouped count.
    $this->shippedFulfillmentFor($seller);
    $this->deliveredFulfillmentFor($seller);

    $counts = [];

    foreach (app(FulfillmentLanes::class)->tabs($seller, LaneFilter::ToShip) as $tab) {
        $counts[$tab->lane->value] = $tab->count;
    }

    expect($counts)->toBe([
        LaneFilter::ToShip->value => 2,
        LaneFilter::InProgress->value => 2,
        LaneFilter::Done->value => null,
        LaneFilter::All->value => null,
    ]);
});

it('counts a lane at nothing when it holds nothing', function (): void {
    $tabs = app(FulfillmentLanes::class)->tabs($this->seller(), LaneFilter::ToShip);

    expect($tabs[0]->count)->toBe(0);
});

it('marks the current tab and links every tab to its own lane', function (): void {
    $tabs = app(FulfillmentLanes::class)->tabs($this->seller(), LaneFilter::Done);

    expect(array_map(fn (LaneTab $tab): bool => $tab->active, $tabs))->toBe([false, false, true, false])
        ->and($tabs[1]->href)->toContain('lane=progress');
});

it('counts another sellers parcels into their own lanes', function (): void {
    $this->paidFulfillmentFor($this->seller('Rye Press'));

    $tabs = app(FulfillmentLanes::class)->tabs($this->seller('Blue Kiln Studio'), LaneFilter::ToShip);

    expect($tabs[0]->count)->toBe(0);
});

it('reads the oldest parcel first while a buyer is waiting', function (): void {
    $seller = $this->seller();
    $older = $this->paidFulfillmentFor($seller);
    $newer = $this->paidFulfillmentFor($seller);
    $older->forceFill(['created_at' => $this->moment('2026-08-18 09:00:00')])->save();
    $newer->forceFill(['created_at' => $this->moment('2026-08-22 09:00:00')])->save();

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::ToShip);

    expect(array_map(fn (OrderRow $row): string => $row->id, $pane->rows))->toBe([$older->id, $newer->id]);
});

it('reads the newest parcel first in every other lane', function (): void {
    $seller = $this->seller();
    $older = $this->paidFulfillmentFor($seller);
    $newer = $this->paidFulfillmentFor($seller);
    $older->forceFill(['created_at' => $this->moment('2026-08-18 09:00:00')])->save();
    $newer->forceFill(['created_at' => $this->moment('2026-08-22 09:00:00')])->save();

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::All);

    expect(array_map(fn (OrderRow $row): string => $row->id, $pane->rows))->toBe([$newer->id, $older->id]);
});

it('carries the row facts a seller scans by', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 45000);

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::ToShip, $fulfillment);
    $row = $pane->rows[0];

    expect($row->buyer)->toBe('Ada Lovelace')
        ->and($row->subtotal)->toBe('$450.00')
        ->and($row->statusLabel)->toBe('Awaiting shipment')
        ->and($row->tint)->toBe('yellow')
        ->and($row->placed)->toBe('Aug 20')
        ->and($row->selected)->toBeTrue()
        ->and($row->href)->toContain('lane=ship')
        ->and($row->note)->toBeNull();
});

it('notes the last step the seller marked done', function () use ($flowWithOneStep): void {
    $seller = $this->seller('Luna Lovegood');
    $step = $flowWithOneStep($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::InProgress);

    expect($pane->rows[0]->note)->toBe('Label printed');
});

it('notes what the buyer asked and nobody answered over the step behind it', function () use ($flowWithOneStep): void {
    $seller = $this->seller('Arthur Weasley');
    $step = $flowWithOneStep($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $conversation = Conversation::create([
        'kind' => ConversationKind::Fulfillment,
        'seller_id' => $seller->id,
        'customer_id' => $fulfillment->customer_id,
        'fulfillment_id' => $fulfillment->id,
        'order_id' => $fulfillment->order_id,
        'last_message_at' => $this->moment('2026-08-22 09:00:00'),
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => ActorType::Customer->value,
        'sender_id' => $fulfillment->customer_id,
        'body' => 'Could you wrap it as a gift?',
        'sent_at' => $this->moment('2026-08-22 09:00:00'),
    ]);

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::InProgress);

    expect($pane->rows[0]->note)->toBe('Could you wrap it as a gift?');
});

it('leaves a message the seller has read off the row', function (): void {
    $seller = $this->seller('Ginny Weasley');
    $fulfillment = $this->paidFulfillmentFor($seller);

    $conversation = Conversation::create([
        'kind' => ConversationKind::Fulfillment,
        'seller_id' => $seller->id,
        'customer_id' => $fulfillment->customer_id,
        'fulfillment_id' => $fulfillment->id,
        'order_id' => $fulfillment->order_id,
        'last_message_at' => $this->moment('2026-08-22 09:00:00'),
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => ActorType::Customer->value,
        'sender_id' => $fulfillment->customer_id,
        'body' => 'Could you wrap it as a gift?',
        'sent_at' => $this->moment('2026-08-22 09:00:00'),
        'read_at' => $this->moment('2026-08-22 10:00:00'),
    ]);

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::ToShip);

    expect($pane->rows[0]->note)->toBeNull();
});

it('holds the open parcel in the pane whatever the lane it sits in', function (): void {
    $seller = $this->seller();
    $open = $this->deliveredFulfillmentFor($seller);

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::ToShip, $open);

    expect($pane->rows)->toBeEmpty();

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::Done, $open);

    expect($pane->rows[0]->id)->toBe($open->id);
});

it('counts what the lane holds beyond the window it hands back', function (): void {
    $seller = $this->seller();
    $this->paidFulfillmentFor($seller);

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::ToShip);

    expect($pane->total)->toBe(1)
        ->and($pane->shown())->toBe(1)
        ->and($pane->isEmpty())->toBeFalse();
});

it('leaves another sellers parcels out of the pane', function (): void {
    $mine = $this->seller('Blue Kiln Studio');
    $this->paidFulfillmentFor($this->seller('Rye Press'));

    expect(app(FulfillmentLanes::class)->pane($mine, LaneFilter::All)->rows)->toBeEmpty();
});

it('names the seller lines on a row holding more than one', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor(
        $this->verifiedCustomer(),
        $this->listing($seller, ['title' => 'Harbour at Dusk']),
        $this->listing($seller, ['title' => 'Kiln Study']),
    );
    app(\App\Actions\Orders\FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::ToShip);

    expect($pane->rows[0]->itemLabel)->toBe('Harbour at Dusk +1 more');
});

it('holds an empty pane for a lane with nothing in it', function (): void {
    $pane = app(FulfillmentLanes::class)->pane($this->seller(), LaneFilter::Done);

    expect($pane->isEmpty())->toBeTrue()
        ->and($pane->total)->toBe(0)
        ->and($pane->lane)->toBe(LaneFilter::Done);
});

it('marks no row selected when nothing is open', function (): void {
    $seller = $this->seller();
    $this->paidFulfillmentFor($seller);

    $pane = app(FulfillmentLanes::class)->pane($seller, LaneFilter::ToShip);

    expect($pane->rows[0]->selected)->toBeFalse();
});

it('counts each lane at what the lane itself selects', function () use ($flowWithOneStep): void {
    $seller = $this->seller('Percy Weasley');
    $step = $flowWithOneStep($seller);
    $started = $this->paidFulfillmentFor($seller);
    app(CompleteFlowStep::class)($started, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));
    $this->paidFulfillmentFor($seller);
    $this->shippedFulfillmentFor($seller);
    $this->deliveredFulfillmentFor($seller);

    foreach (app(FulfillmentLanes::class)->tabs($seller, LaneFilter::ToShip) as $tab) {
        if ($tab->count === null) {
            continue;
        }

        expect($tab->count)->toBe(Fulfillment::query()->whereBelongsTo($seller)->inLane($tab->lane)->count());
    }
});

it('reads a pane of many rows in the same number of queries as a pane of one', function () use ($flowWithOneStep): void {
    $seller = $this->seller('Bill Weasley');
    $step = $flowWithOneStep($seller);
    $lanes = app(FulfillmentLanes::class);

    $addParcelWithNote = function () use ($seller, $step): void {
        $fulfillment = $this->paidFulfillmentFor($seller);
        app(CompleteFlowStep::class)($fulfillment, $step, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));
        $conversation = Conversation::create([
            'kind' => ConversationKind::Fulfillment,
            'seller_id' => $seller->id,
            'customer_id' => $fulfillment->customer_id,
            'fulfillment_id' => $fulfillment->id,
            'order_id' => $fulfillment->order_id,
            'last_message_at' => $this->moment('2026-08-22 09:00:00'),
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => ActorType::Customer->value,
            'sender_id' => $fulfillment->customer_id,
            'body' => 'Could you wrap it as a gift?',
            'sent_at' => $this->moment('2026-08-22 09:00:00'),
        ]);
    };

    $queriesForPane = function () use ($lanes, $seller): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $lanes->pane($seller, LaneFilter::All);

        return $queries;
    };

    $addParcelWithNote();
    $withOne = $queriesForPane();

    $addParcelWithNote();
    $addParcelWithNote();
    $addParcelWithNote();
    $addParcelWithNote();
    $withFive = $queriesForPane();

    expect($lanes->pane($seller, LaneFilter::All)->rows)->toHaveCount(5)
        ->and($withFive)->toBe($withOne);
});
