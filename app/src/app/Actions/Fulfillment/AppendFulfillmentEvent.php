<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Fulfillment\NewFulfillmentEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;

/**
 * The one writer of `fulfillment_events`. Every transition action calls it
 * inside the transaction that writes `fulfillments.status`, so a status that
 * moved without its event cannot commit.
 *
 * The step's words are copied onto the row: a seller who removes the step
 * from their flow later leaves the log still saying what they did.
 */
final readonly class AppendFulfillmentEvent
{
    public function __invoke(Fulfillment $fulfillment, NewFulfillmentEvent $event): FulfillmentEvent
    {
        return FulfillmentEvent::create([
            'fulfillment_id' => $fulfillment->id,
            'seller_id' => $fulfillment->seller_id,
            'kind' => $event->kind,
            'fulfillment_flow_step_id' => $event->step?->id,
            'step_label' => $event->step?->label,
            'actor_type' => $event->actorType,
            'actor_id' => $event->actorId,
            'carrier' => $event->carrier,
            'tracking_number' => $event->trackingNumber,
            'occurred_at' => $event->occurredAt,
        ]);
    }
}
