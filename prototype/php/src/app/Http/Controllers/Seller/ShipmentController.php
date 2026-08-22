<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\MarkShipped;
use App\Domain\Orders\FulfillmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\MarkShippedRequest;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentController extends Controller
{
    public function __invoke(MarkShippedRequest $request, string $fulfillment, MarkShipped $markShipped): RedirectResponse
    {
        $fulfillment = auth('seller')->user()->fulfillments()->findOrFail($fulfillment);

        abort_unless($fulfillment->status->canTransitionTo(FulfillmentStatus::Shipped), Response::HTTP_UNPROCESSABLE_ENTITY);

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
