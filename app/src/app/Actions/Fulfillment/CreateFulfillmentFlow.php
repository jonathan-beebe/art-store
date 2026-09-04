<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;

/**
 * A seller's new way of getting a parcel out the door. Their first flow
 * becomes the default, since a listing that names none has to ship by
 * something; every flow after it waits for "make default" to take the role.
 */
final readonly class CreateFulfillmentFlow
{
    public function __construct(private SaveFulfillmentFlow $saveFlow) {}

    /**
     * @param  list<FlowStepDraft>  $drafts
     */
    public function __invoke(Seller $seller, string $name, array $drafts): FulfillmentFlow
    {
        return DB::transaction(function () use ($seller, $name, $drafts): FulfillmentFlow {
            // Locked so two concurrent creates for a seller with none yet
            // cannot both read "no flows" and both write a default —
            // Listing::lockedForPlacement holds the same shape of read.
            $flow = FulfillmentFlow::create([
                'seller_id' => $seller->id,
                'name' => $name,
                'is_default' => $seller->fulfillmentFlows()->lockForUpdate()->doesntExist(),
            ]);

            return ($this->saveFlow)($flow, $name, $drafts);
        });
    }
}
