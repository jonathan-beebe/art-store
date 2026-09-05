<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Http\Requests\Seller\DeclineFulfillmentRequest;
use App\Models\Fulfillment;
use Illuminate\Http\RedirectResponse;

final class DeclineController extends SellerController
{
    public function __invoke(
        DeclineFulfillmentRequest $request,
        Fulfillment $fulfillment,
        DeclineFulfillment $declineFulfillment,
    ): RedirectResponse {
        $declineFulfillment($fulfillment, $request->reason(), $this->now());

        return redirect()
            ->route('seller.orders.show', $fulfillment->id)
            ->with('status', 'Declined and refunded.');
    }
}
