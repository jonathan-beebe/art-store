<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmDelivered
{
    public function __construct(private RollUpOrderStatus $rollUpOrderStatus) {}

    public function __invoke(Fulfillment $fulfillment, DateTimeImmutable $now): Fulfillment
    {
        $status = $fulfillment->status->transitionTo(FulfillmentStatus::Delivered);

        return DB::transaction(function () use ($fulfillment, $status, $now): Fulfillment {
            $fulfillment->update(['status' => $status, 'delivered_at' => $now]);

            $movement = LedgerMovement::release($fulfillment->net());

            LedgerEntry::create([
                'seller_id' => $fulfillment->seller_id,
                'fulfillment_id' => $fulfillment->id,
                'type' => $movement->type,
                'amount_cents' => $movement->amount->cents,
                'occurred_at' => $now,
            ]);

            $fulfillment->load('order.fulfillments');

            ($this->rollUpOrderStatus)($fulfillment->order);

            return $fulfillment;
        });
    }
}
