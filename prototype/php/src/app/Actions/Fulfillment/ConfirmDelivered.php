<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\Auth\ActorType;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\NewFulfillmentEvent;
use App\Domain\Orders\FulfillmentStatus;
use App\Logging\StoryEvent;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmDelivered
{
    public function __construct(
        private RollUpOrderStatus $rollUpOrderStatus,
        private AppendFulfillmentEvent $appendEvent,
    ) {}

    public function __invoke(Fulfillment $fulfillment, DateTimeImmutable $now): Fulfillment
    {
        return Story::for(StoryEvent::FulfillmentDeliver)->tell('confirming a fulfillment delivered', [
            'fulfillment_id' => $fulfillment->id,
            'order_id' => $fulfillment->order_id,
            'status_from' => $fulfillment->status->value,
            'status_to' => FulfillmentStatus::Delivered->value,
        ], function (Story $story) use ($fulfillment, $now): Fulfillment {
            $delivered = DB::transaction(function () use ($fulfillment, $now): Fulfillment {
                // Judged inside the transaction that writes, against a row
                // held for update (docs/alignment.md §4.1): the status the
                // refusal reads is the status this update replaces, and the
                // escrow release below cannot be written twice for one row.
                $status = $fulfillment->takeForTransition()->status->transitionTo(FulfillmentStatus::Delivered);

                $fulfillment->update(['status' => $status, 'delivered_at' => $now]);

                $movement = LedgerMovement::release($fulfillment->net());

                LedgerEntry::create([
                    'seller_id' => $fulfillment->seller_id,
                    'fulfillment_id' => $fulfillment->id,
                    'type' => $movement->type,
                    'amount_cents' => $movement->amount->cents,
                    'occurred_at' => $now,
                ]);

                ($this->appendEvent)($fulfillment, NewFulfillmentEvent::transition(
                    kind: FulfillmentEventKind::forStatus($status),
                    actorType: ActorType::Customer,
                    actorId: $fulfillment->customer_id,
                    occurredAt: $now,
                ));

                $fulfillment->load('order.fulfillments');

                ($this->rollUpOrderStatus)($fulfillment->order);

                return $fulfillment;
            });

            $story->did('confirmed the fulfillment delivered', [
                'fulfillment_id' => $delivered->id,
                'order_id' => $delivered->order_id,
                'status_to' => $delivered->status->value,
                'order_status' => $delivered->order->status->value,
                'released_cents' => $delivered->net()->cents,
            ]);

            return $delivered;
        });
    }
}
