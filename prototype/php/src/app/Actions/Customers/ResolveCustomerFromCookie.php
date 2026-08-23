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

        return Customer::find($this->follow($id));
    }

    /**
     * Walks recorded merges forward until a customer id nothing else points
     * at is reached. `$seen` stops a cycle from looping forever; none forms
     * through the ordinary merge flow, but a chain reaching further than one
     * hop of stale data should still resolve rather than stop early.
     *
     * @param  list<int>  $seen
     */
    private function follow(int $id, array $seen = []): int
    {
        if (in_array($id, $seen, true)) {
            return $id;
        }

        $mergedInto = CustomerMerge::where('anonymous_customer_id', $id)->value('customer_id');
        $mergedIntoId = filter_var($mergedInto, FILTER_VALIDATE_INT);

        return $mergedIntoId === false ? $id : $this->follow($mergedIntoId, [...$seen, $id]);
    }
}
