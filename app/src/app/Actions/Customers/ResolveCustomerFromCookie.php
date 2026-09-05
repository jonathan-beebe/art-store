<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\Identifiers\PrefixedId;
use App\Models\Customer;
use App\Models\CustomerMerge;

final readonly class ResolveCustomerFromCookie
{
    /**
     * @return Customer|null null when the cookie is absent, holds something
     *                       that is not a customer id, or points at a
     *                       customer that no longer exists
     */
    public function __invoke(?string $cookieValue): ?Customer
    {
        if ($cookieValue === null || PrefixedId::parse(Customer::idPrefix(), $cookieValue) === null) {
            return null;
        }

        return Customer::find($this->follow($cookieValue));
    }

    /**
     * Walks recorded merges forward until a customer id nothing else points
     * at is reached. `$seen` stops a cycle from looping forever; none forms
     * through the ordinary merge flow, but a chain reaching further than one
     * hop of stale data should still resolve.
     *
     * @param  list<string>  $seen
     */
    private function follow(string $id, array $seen = []): string
    {
        if (in_array($id, $seen, true)) {
            return $id;
        }

        $mergedInto = CustomerMerge::where('anonymous_customer_id', $id)->value('customer_id');

        return is_string($mergedInto) ? $this->follow($mergedInto, [...$seen, $id]) : $id;
    }
}
