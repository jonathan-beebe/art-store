<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\NewFulfillmentEvent;
use App\Domain\Orders\FulfillmentStatus;
use App\Events\FulfillmentShipped;
use App\Logging\StoryEvent;
use App\Models\Fulfillment;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class MarkShipped
{
    public function __construct(
        private RollUpOrderStatus $rollUpOrderStatus,
        private AppendFulfillmentEvent $appendEvent,
    ) {}

    public function __invoke(
        Fulfillment $fulfillment,
        string $carrier,
        string $trackingNumber,
        DateTimeImmutable $now,
    ): Fulfillment {
        return Story::for(StoryEvent::FulfillmentShip)->tell('marking a fulfillment shipped', [
            'fulfillment_id' => $fulfillment->id,
            'order_id' => $fulfillment->order_id,
            'status_from' => $fulfillment->status->value,
            'status_to' => FulfillmentStatus::Shipped->value,
        ], function (Story $story) use ($fulfillment, $carrier, $trackingNumber, $now): Fulfillment {
            $shipped = DB::transaction(function () use ($fulfillment, $carrier, $trackingNumber, $now): Fulfillment {
                // Judged inside the transaction that writes, against a row
                // held for update (docs/spec.md §4.1): the status the
                // refusal reads is the status this update replaces.
                $status = $fulfillment->takeForTransition()->status->transitionTo(FulfillmentStatus::Shipped);

                $fulfillment->update([
                    'status' => $status,
                    'carrier' => $carrier,
                    'tracking_number' => $trackingNumber,
                    'shipped_at' => $now,
                ]);

                ($this->appendEvent)($fulfillment, NewFulfillmentEvent::transition(
                    kind: FulfillmentEventKind::forStatus($status),
                    actorType: ActorType::Seller,
                    actorId: $fulfillment->seller_id,
                    occurredAt: $now,
                ));

                $fulfillment->load('order.fulfillments');

                ($this->rollUpOrderStatus)($fulfillment->order);

                FulfillmentShipped::dispatch($fulfillment, $now);

                return $fulfillment;
            });

            $story->did('marked the fulfillment shipped', [
                'fulfillment_id' => $shipped->id,
                'order_id' => $shipped->order_id,
                'carrier' => $carrier,
                'status_to' => $shipped->status->value,
                'order_status' => $shipped->order->status->value,
            ]);

            return $shipped;
        });
    }
}
