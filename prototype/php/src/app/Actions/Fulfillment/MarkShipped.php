<?php

namespace App\Actions\Fulfillment;

use App\Actions\Notifications\Notify;
use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class MarkShipped
{
    public function __construct(
        private readonly RollUpOrderStatus $rollUpOrderStatus,
        private readonly Notify $notify,
    ) {}

    public function __invoke(
        Fulfillment $fulfillment,
        string $carrier,
        string $trackingNumber,
        DateTimeImmutable $now,
    ): Fulfillment {
        $status = $fulfillment->status->transitionTo(FulfillmentStatus::Shipped);

        return DB::transaction(function () use ($fulfillment, $status, $carrier, $trackingNumber, $now): Fulfillment {
            $fulfillment->update([
                'status' => $status,
                'carrier' => $carrier,
                'tracking_number' => $trackingNumber,
                'shipped_at' => $now,
            ]);

            $order = ($this->rollUpOrderStatus)($fulfillment->order);

            ($this->notify)(
                RecipientType::Customer,
                $order->customer_id,
                NotificationMessage::orderShipped($order->id, $carrier, $trackingNumber),
            );

            return $fulfillment;
        });
    }
}
