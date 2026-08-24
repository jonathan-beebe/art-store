<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Escrow\IssueRefund;
use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\Auth\ActorType;
use App\Domain\Orders\FulfillmentStatus;
use App\Logging\StoryEvent;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Refund;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * An admin sends a fulfillment's money back: a dispute settled after the
 * parcel shipped or arrived, or a seller who never answered an awaiting
 * shipment. Stock stays sold — the pieces are with the customer, or with a
 * seller who is not answering for them.
 */
final readonly class RefundFulfillment
{
    public function __construct(
        private IssueRefund $issueRefund,
        private RollUpOrderStatus $rollUpOrderStatus,
    ) {}

    public function __invoke(Fulfillment $fulfillment, Admin $admin, string $reason, DateTimeImmutable $now): Fulfillment
    {
        return Story::for(StoryEvent::RefundIssue)->tell('refunding a fulfillment', [
            'fulfillment_id' => $fulfillment->id,
            'order_id' => $fulfillment->order_id,
            'status_from' => $fulfillment->status->value,
            'status_to' => FulfillmentStatus::Refunded->value,
        ], function (Story $story) use ($fulfillment, $admin, $reason, $now): Fulfillment {
            $refund = DB::transaction(function () use ($fulfillment, $admin, $reason, $now): Refund {
                // Judged inside the transaction that writes, against a row
                // held for update (docs/alignment.md §4.1), so a fulfillment
                // refunded from two consoles is refused the second time
                // rather than breaking on `refunds.unique(fulfillment_id)`.
                $status = $fulfillment->takeForTransition()->status->transitionTo(FulfillmentStatus::Refunded);

                $fulfillment->update(['status' => $status]);

                $refund = ($this->issueRefund)($fulfillment, ActorType::Admin, $admin->id, $reason, $now);

                ($this->rollUpOrderStatus)($fulfillment->load('order.fulfillments')->order);

                return $refund;
            });

            $story->did('issued the refund', [
                'refund_id' => $refund->id,
                'fulfillment_id' => $fulfillment->id,
                'order_id' => $fulfillment->order_id,
                'amount_cents' => $refund->amount_cents,
                'status_to' => $fulfillment->status->value,
                'order_status' => $fulfillment->order->status->value,
                'reason' => $reason,
            ]);

            return $fulfillment;
        });
    }
}
