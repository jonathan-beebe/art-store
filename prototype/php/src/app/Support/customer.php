<?php

use App\Models\Customer;
use App\Support\CustomerIdentity;

if (! function_exists('customer')) {
    /**
     * The storefront visitor behind this request, anonymous or verified.
     * Null anywhere the ResolveCustomerIdentity middleware has not run.
     */
    function customer(): ?Customer
    {
        return CustomerIdentity::current();
    }
}
