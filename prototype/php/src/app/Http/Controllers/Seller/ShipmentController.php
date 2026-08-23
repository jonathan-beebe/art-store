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
        $this->authorize('update', $fulfillment);

        $markShipped(
            $fulfillment,
            $request->string('carrier')->toString(),
            $request->string('tracking_number')->toString(),
            $this->now(),
        );

        return redirect()
            ->route('seller.orders.show', $fulfillment->id)
            ->with('status', 'Marked shipped.');
    }
}
