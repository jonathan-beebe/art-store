<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerMerge;

final readonly class ResolveCustomerFromCookie
{
    /**
     * @return Customer|null null when the cookie is absent, unreadable, or
     *                       points at a customer that no longer exists
     */
    public function __invoke(?string $cookieValue): ?Customer
    {
        $id = filter_var($cookieValue, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            return null;
        }

        $mergedInto = CustomerMerge::where('anonymous_customer_id', $id)->value('customer_id');
        $mergedIntoId = filter_var($mergedInto, FILTER_VALIDATE_INT);

        return Customer::find($mergedIntoId === false ? $id : $mergedIntoId);
    }
}
