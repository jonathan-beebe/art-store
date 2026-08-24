<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\DomainRuleViolation;
use App\Domain\Orders\FulfillmentStatus;
use App\Events\FulfillmentShipped;
use App\Logging\StoryEvent;
use App\Models\Fulfillment;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class MarkShipped
{
    public function __construct(private RollUpOrderStatus $rollUpOrderStatus) {}

    public function __invoke(
        Fulfillment $fulfillment,
        string $carrier,
        string $trackingNumber,
        DateTimeImmutable $now,
    ): Fulfillment {
        $story = Story::for(StoryEvent::FulfillmentShip)->will('marking a fulfillment shipped', [
            'fulfillment_id' => $fulfillment->id,
            'order_id' => $fulfillment->order_id,
            'status_from' => $fulfillment->status->value,
            'status_to' => FulfillmentStatus::Shipped->value,
        ]);

        try {
            $status = $fulfillment->status->transitionTo(FulfillmentStatus::Shipped);
        } catch (DomainRuleViolation $violation) {
            $story->refused($violation->getMessage(), ['fulfillment_id' => $fulfillment->id]);

            throw $violation;
        }

        $shipped = DB::transaction(function () use ($fulfillment, $status, $carrier, $trackingNumber, $now): Fulfillment {
            $fulfillment->update([
                'status' => $status,
                'carrier' => $carrier,
                'tracking_number' => $trackingNumber,
                'shipped_at' => $now,
            ]);

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
    }
}
