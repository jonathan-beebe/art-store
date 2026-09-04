<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\MakeFulfillmentFlowDefault;
use App\Models\FulfillmentFlow;
use Illuminate\Http\RedirectResponse;

/**
 * A seller hands the default role to one of their workflows: the one a
 * listing that names none ships by.
 */
final class MakeFulfillmentFlowDefaultController extends SellerController
{
    public function __invoke(FulfillmentFlow $workflow, MakeFulfillmentFlowDefault $makeDefault): RedirectResponse
    {
        $this->authorize('update', $workflow);

        $makeDefault($workflow);

        return redirect()->route('seller.workflows.index')->with('status', 'Default workflow set.');
    }
}
