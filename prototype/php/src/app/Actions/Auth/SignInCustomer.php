<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Customers\ClaimCustomerIdentity;
use App\Models\Customer;
use App\Support\CustomerIdentity;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;

final readonly class SignInCustomer
{
    public function __construct(private ClaimCustomerIdentity $claim) {}

    /**
     * @param  Customer|null  $current  the customer the identity cookie points at
     */
    public function __invoke(string $email, ?Customer $current, DateTimeImmutable $now): Customer
    {
        $customer = ($this->claim)($email, $current, $now);

        Auth::guard('customer')->login($customer);
        CustomerIdentity::rememberInCookie($customer);

        return $customer;
    }
}
