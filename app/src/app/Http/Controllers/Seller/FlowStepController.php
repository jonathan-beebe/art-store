<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\CompleteFlowStep;
use App\Http\Requests\Seller\CompleteFlowStepRequest;
use App\Models\Fulfillment;
use App\Models\FulfillmentFlowStep;
use Illuminate\Http\RedirectResponse;

/**
 * The seller marks one step of their flow done. A step that prints a label
 * hands the printable page back; every other step returns to the order.
 */
final class FlowStepController extends SellerController
{
    public function __invoke(
        CompleteFlowStepRequest $request,
        Fulfillment $fulfillment,
        FulfillmentFlowStep $step,
        CompleteFlowStep $completeFlowStep,
    ): RedirectResponse {
        $completeFlowStep(
            $fulfillment,
            $step,
            $request->carrier(),
            $request->trackingNumber(),
            $this->now(),
        );

        return $step->action->printsLabel()
            ? redirect()->route('seller.orders.label', $fulfillment->id)
            : redirect()->route('seller.orders.show', $fulfillment->id)->with('status', $step->label.' — recorded.');
    }
}
