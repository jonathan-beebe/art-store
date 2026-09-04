<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use Illuminate\View\View;

/**
 * The printable label for one parcel: the buyer's address as they gave it at
 * checkout, and the carrier and tracking number the label step recorded. A
 * real PDF is a carrier integration away.
 */
final class ShippingLabelController extends SellerController
{
    public function __invoke(Fulfillment $fulfillment): View
    {
        $this->authorize('view', $fulfillment);

        $fulfillment->load(['order', 'fulfillmentEvents']);

        return view('seller.orders.label', [
            'fulfillment' => $fulfillment,
            'addressLines' => $fulfillment->order->shippingAddressLines(),
            'shipment' => $this->latestLabelEvent($fulfillment),
        ]);
    }

    /**
     * The label this page prints: the most recent step completion that
     * carried a carrier.
     */
    private function latestLabelEvent(Fulfillment $fulfillment): ?FulfillmentEvent
    {
        return $fulfillment->fulfillmentEvents
            ->where('kind', FulfillmentEventKind::StepCompleted)
            ->filter(fn (FulfillmentEvent $event): bool => $event->carrier !== null)
            ->last();
    }
}
