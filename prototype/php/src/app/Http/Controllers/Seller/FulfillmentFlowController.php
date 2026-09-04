<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\SaveFulfillmentFlow;
use App\Domain\Fulfillment\DefaultFlow;
use App\Http\Requests\Seller\UpdateFulfillmentFlowRequest;
use App\Models\FulfillmentFlow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The seller's own flow: the ordered steps every parcel of theirs goes
 * through. One page, one form, one save.
 */
final class FulfillmentFlowController extends SellerController
{
    public function edit(): View
    {
        $flow = $this->seller()->loadMissing('defaultFulfillmentFlow')->defaultFulfillmentFlow;

        return view('seller.orders.flow.edit', [
            'name' => $flow instanceof FulfillmentFlow ? $flow->name : DefaultFlow::NAME,
            'steps' => $flow instanceof FulfillmentFlow ? $flow->load('steps')->steps : new Collection,
        ]);
    }

    public function update(UpdateFulfillmentFlowRequest $request, SaveFulfillmentFlow $saveFlow): RedirectResponse
    {
        $saveFlow($this->seller(), $request->name(), $request->drafts());

        return redirect()->route('seller.orders.flow.edit')->with('status', 'Flow saved.');
    }
}
