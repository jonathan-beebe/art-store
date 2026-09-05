<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Seller\CustomerRow;
use App\Models\Customer;
use App\Models\Seller;
use App\Seller\SellerCustomers;
use Illuminate\Auth\Access\Response;

/**
 * A customer belongs to no seller directly — a buyer can hold parcels with
 * many. A seller's customer is one holding at least one paid parcel with
 * them that still stands, the same rule the customers list itself reads
 * by; a buyer outside it answers "not found", so a customer id from
 * another seller's portal is never confirmed to exist.
 */
final class CustomerPolicy
{
    public function view(Seller $seller, Customer $customer): Response
    {
        return SellerCustomers::forCustomer($seller, $customer) instanceof CustomerRow
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
