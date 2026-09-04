<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\CreateFulfillmentFlow;
use App\Actions\Fulfillment\DeleteFulfillmentFlow;
use App\Actions\Fulfillment\SaveFulfillmentFlow;
use App\Http\Requests\Seller\FulfillmentFlowRequest;
use App\Models\FulfillmentFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * A seller's workflows: the ordered steps a parcel goes through between
 * paid and shipped, named once and picked per listing.
 */
final class FulfillmentFlowController extends SellerController
{
    public function index(): View
    {
        $flows = $this->seller()->fulfillmentFlows()
            ->withCount('steps')
            ->with('listings')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('seller.workflows.index', ['flows' => $flows]);
    }

    public function create(): View
    {
        return view('seller.workflows.create');
    }

    public function store(FulfillmentFlowRequest $request, CreateFulfillmentFlow $createFlow): RedirectResponse
    {
        $flow = $createFlow($this->seller(), $request->name(), $request->drafts());

        return redirect()->route('seller.workflows.edit', $flow)->with('status', 'Workflow added.');
    }

    public function edit(FulfillmentFlow $workflow): View
    {
        $this->authorize('update', $workflow);

        return view('seller.workflows.edit', ['workflow' => $workflow->load('steps')]);
    }

    public function update(FulfillmentFlowRequest $request, FulfillmentFlow $workflow, SaveFulfillmentFlow $saveFlow): RedirectResponse
    {
        $saveFlow($workflow, $request->name(), $request->drafts());

        return redirect()->route('seller.workflows.edit', $workflow)->with('status', 'Workflow saved.');
    }

    public function destroy(FulfillmentFlow $workflow, DeleteFulfillmentFlow $deleteFlow): RedirectResponse
    {
        $this->authorize('update', $workflow);

        $deleteFlow($workflow);

        return redirect()->route('seller.workflows.index')->with('status', 'Workflow removed.');
    }
}
