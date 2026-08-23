<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\MarkShipped;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\MarkShippedRequest;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;

final class ShipmentController extends Controller
{
    public function __invoke(MarkShippedRequest $request, string $fulfillment, MarkShipped $markShipped): RedirectResponse
    {
        $fulfillment = auth('seller')->user()->fulfillments()->findOrFail($fulfillment);

        $markShipped(
            $fulfillment,
            $request->string('carrier')->toString(),
            $request->string('tracking_number')->toString(),
            new DateTimeImmutable(now()->toDateTimeString()),
        );

        return redirect()
            ->route('seller.orders.show', $fulfillment->id)
            ->with('status', 'Marked shipped.');
    }
}
