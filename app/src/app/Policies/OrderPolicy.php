<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Auth\Access\Response;

/**
 * Someone else's order is not theirs to read, pay, cancel, or receive, and
 * saying "not found" tells them nothing about whether it exists.
 */
final class OrderPolicy
{
    public function view(Customer $customer, Order $order): Response
    {
        return $this->ownership($customer, $order);
    }

    public function pay(Customer $customer, Order $order): Response
    {
        return $this->ownership($customer, $order);
    }

    /**
     * Cancelling is the customer's while nothing has been charged. Past that
     * the path is a refund, which only a seller or an admin can take. The
     * button this answers for is on the order page; `CancelOrder` holds the
     * same rule for the write.
     */
    public function cancel(Customer $customer, Order $order): Response
    {
        $ownership = $this->ownership($customer, $order);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $order->status->canTransitionTo(OrderStatus::Cancelled) ? Response::allow() : Response::deny();
    }

    private function ownership(Customer $customer, Order $order): Response
    {
        return $order->customer_id === $customer->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
