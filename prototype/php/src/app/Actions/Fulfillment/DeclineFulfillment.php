<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Escrow\IssueRefund;
use App\Actions\Orders\RollUpOrderStatus;
use App\Domain\Auth\ActorType;
use App\Domain\Orders\FulfillmentStatus;
use App\Logging\StoryEvent;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Unit;
use App\Models\Variant;
use App\Support\Orders\StockMovement;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * The seller turns a parcel down before it ships: the customer's money goes
 * back and the pieces return to the storefront. Only the seller who owns the
 * fulfillment can, and only while it is still awaiting shipment.
 */
final readonly class DeclineFulfillment
{
    public function __construct(
        private IssueRefund $issueRefund,
        private RollUpOrderStatus $rollUpOrderStatus,
    ) {}

    public function __invoke(Fulfillment $fulfillment, string $reason, DateTimeImmutable $now): Fulfillment
    {
        return Story::for(StoryEvent::FulfillmentDecline)->tell('declining a fulfillment', [
            'fulfillment_id' => $fulfillment->id,
            'order_id' => $fulfillment->order_id,
            'status_from' => $fulfillment->status->value,
            'status_to' => FulfillmentStatus::Declined->value,
        ], function (Story $story) use ($fulfillment, $reason, $now): Fulfillment {
            $declined = DB::transaction(function () use ($fulfillment, $reason, $now): Fulfillment {
                // Judged inside the transaction that writes, against a row
                // held for update (docs/alignment.md §4.1): a parcel shipped
                // between the page and this submit is refused here rather
                // than declined after it left, and a second console cannot
                // read the same pre-decline status while this one holds it.
                $status = $fulfillment->takeForTransition()->status->transitionTo(FulfillmentStatus::Declined);

                $this->restockItems($fulfillment);

                $fulfillment->update(['status' => $status]);

                $refund = ($this->issueRefund)($fulfillment, ActorType::Seller, $fulfillment->seller_id, $reason, $now);

                Story::for(StoryEvent::RefundIssue)->did('issued the refund', [
                    'refund_id' => $refund->id,
                    'fulfillment_id' => $fulfillment->id,
                    'amount_cents' => $refund->amount_cents,
                    'reason' => $reason,
                ]);

                ($this->rollUpOrderStatus)($fulfillment->load('order.fulfillments')->order);

                return $fulfillment;
            });

            $story->did('declined the fulfillment', [
                'fulfillment_id' => $declined->id,
                'order_id' => $declined->order_id,
                'status_to' => $declined->status->value,
                'order_status' => $declined->order->status->value,
                'reason' => $reason,
            ]);

            return $declined;
        });
    }

    /**
     * Puts this seller's lines back on the storefront — and a listing that
     * sold out because of them back on sale — from rows read for update, so
     * the quantity written is the quantity read. The order's other sellers'
     * lines are read under the same lock and left alone: their parcels are
     * still coming.
     */
    private function restockItems(Fulfillment $fulfillment): void
    {
        $order = $fulfillment->loadMissing('order')->order;

        $order->load([
            'items.listing' => $this->takeForUpdate(...),
            'items.variant' => $this->takeForUpdateVariant(...),
            'items.unit' => $this->takeForUpdateUnit(...),
        ]);

        foreach ($order->items as $item) {
            if ($item->seller_id === $fulfillment->seller_id) {
                StockMovement::release($item);
            }
        }
    }

    /**
     * @param  BelongsTo<Listing, OrderItem>  $listing
     */
    private function takeForUpdate(BelongsTo $listing): void
    {
        $listing->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Variant, OrderItem>  $variant
     */
    private function takeForUpdateVariant(BelongsTo $variant): void
    {
        $variant->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Unit, OrderItem>  $unit
     */
    private function takeForUpdateUnit(BelongsTo $unit): void
    {
        $unit->getQuery()->lockedForPlacement();
    }
}
