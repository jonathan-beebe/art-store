<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Auth\Access\Response;

/**
 * Someone else's order is not theirs to read, pay, or receive, and saying
 * "not found" tells them nothing about whether it exists.
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

    private function ownership(Customer $customer, Order $order): Response
    {
        return $order->customer_id === $customer->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
