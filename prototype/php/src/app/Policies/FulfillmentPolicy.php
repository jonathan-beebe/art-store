<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;

/**
 * A fulfillment has two owners: the seller who ships it and the customer who
 * receives it. `view` and `update` answer ownership alone — the only question
 * a request has to pass before an action runs, and the one that hides another
 * party's row behind a 404. `ship` and `confirmDelivery` add the state each
 * form needs to be worth offering; the actions behind those forms hold the
 * same rule for the write and phrase their own refusal.
 */
final class FulfillmentPolicy
{
    public function view(Seller $seller, Fulfillment $fulfillment): Response
    {
        return $this->sellerOwnership($seller, $fulfillment);
    }

    public function update(Seller $seller, Fulfillment $fulfillment): Response
    {
        return $this->sellerOwnership($seller, $fulfillment);
    }

    public function ship(Seller $seller, Fulfillment $fulfillment): Response
    {
        return $this->whenAllowed(
            $this->sellerOwnership($seller, $fulfillment),
            $fulfillment->status->canTransitionTo(FulfillmentStatus::Shipped),
        );
    }

    /**
     * A seller can turn a parcel down while it is still in their studio and
     * the order behind it has been paid. Another seller's fulfillment is a
     * 404, the same page as one that does not exist.
     */
    public function decline(Seller $seller, Fulfillment $fulfillment): Response
    {
        return $this->whenAllowed(
            $this->sellerOwnership($seller, $fulfillment),
            $fulfillment->isDeclinable(),
        );
    }

    public function confirmDelivery(Customer $customer, Fulfillment $fulfillment): Response
    {
        return $this->whenAllowed(
            $this->customerOwnership($customer, $fulfillment),
            $fulfillment->status->canTransitionTo(FulfillmentStatus::Delivered),
        );
    }

    /**
     * A row that is not the actor's stays a 404; a state that is not ready
     * yet is a plain refusal, since the actor may see the row either way.
     */
    private function whenAllowed(Response $ownership, bool $isReady): Response
    {
        if ($ownership->denied()) {
            return $ownership;
        }

        return $isReady ? Response::allow() : Response::deny();
    }

    private function sellerOwnership(Seller $seller, Fulfillment $fulfillment): Response
    {
        return $fulfillment->seller_id === $seller->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function customerOwnership(Customer $customer, Fulfillment $fulfillment): Response
    {
        return $fulfillment->order->customer_id === $customer->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
