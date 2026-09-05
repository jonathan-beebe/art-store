<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FulfillmentFlow;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;

/**
 * A flow belongs to one seller. Another seller's flow answers "not found",
 * so an id outside a seller's own workflows is never confirmed to exist.
 */
final class FulfillmentFlowPolicy
{
    public function view(Seller $seller, FulfillmentFlow $flow): Response
    {
        return $this->ownership($seller, $flow);
    }

    public function update(Seller $seller, FulfillmentFlow $flow): Response
    {
        return $this->ownership($seller, $flow);
    }

    private function ownership(Seller $seller, FulfillmentFlow $flow): Response
    {
        return $flow->seller_id === $seller->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
