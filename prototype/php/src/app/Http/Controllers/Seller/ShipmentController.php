<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\MarkShipped;
use App\Http\Requests\Seller\MarkShippedRequest;
use App\Models\Fulfillment;
use Illuminate\Http\RedirectResponse;

final class ShipmentController extends SellerController
{
    public function __invoke(MarkShippedRequest $request, Fulfillment $fulfillment, MarkShipped $markShipped): RedirectResponse
    {
        $markShipped(
            $fulfillment,
            $request->carrier(),
            $request->trackingNumber(),
            $this->now(),
        );

        return redirect()
            ->route('seller.orders.show', $fulfillment->id)
            ->with('status', 'Marked shipped.');
    }
}
