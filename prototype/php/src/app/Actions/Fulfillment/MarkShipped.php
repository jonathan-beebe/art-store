<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Events\FulfillmentShipped;
use App\Models\Fulfillment;
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
        $status = $fulfillment->status->transitionTo(FulfillmentStatus::Shipped);

        return DB::transaction(function () use ($fulfillment, $status, $carrier, $trackingNumber, $now): Fulfillment {
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
    }
}
