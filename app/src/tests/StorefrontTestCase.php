<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Customer;
use App\Shop\CustomerIdentity;

/**
 * Base for the storefront HTTP tests. The identity cookie is what ties a
 * visitor's requests together, and the test client does not keep the one the
 * middleware queues, so a flow that spans requests pins its visitor up front.
 */
abstract class StorefrontTestCase extends CommerceTestCase
{
    public function visitor(): Customer
    {
        return $this->arriveAs($this->anonymousCustomer());
    }

    public function arriveAs(Customer $customer): Customer
    {
        $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id);

        return $customer;
    }
}
