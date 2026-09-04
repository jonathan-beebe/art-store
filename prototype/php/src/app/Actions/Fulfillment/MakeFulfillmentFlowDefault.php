<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Models\FulfillmentFlow;
use Illuminate\Support\Facades\DB;

/**
 * Hands the default role to one flow, taking it from whichever of the
 * seller's flows held it. One transaction: the partial unique index on
 * `fulfillment_flows` admits only one default row per seller at a time, so
 * the old default has to clear before the new one can be set.
 */
final readonly class MakeFulfillmentFlowDefault
{
    public function __invoke(FulfillmentFlow $flow): FulfillmentFlow
    {
        return DB::transaction(function () use ($flow): FulfillmentFlow {
            // Excludes $flow itself: an already-default flow would otherwise
            // be cleared here and then skip the write below, since Eloquent
            // never issues an UPDATE for a value that already matches its
            // in-memory state.
            FulfillmentFlow::query()
                ->where('seller_id', $flow->seller_id)
                ->where('is_default', true)
                ->where('id', '!=', $flow->id)
                ->update(['is_default' => false]);

            $flow->update(['is_default' => true]);

            return $flow;
        });
    }
}
