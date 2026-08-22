<?php

namespace App\Actions\Customers;

use App\Domain\Auth\EmailAddress;
use App\Domain\Customers\CustomerIdentityAction;
use App\Domain\Customers\CustomerIdentityPlan;
use App\Models\Customer;

final readonly class ClaimCustomerIdentity
{
    public function __construct(private MergeAnonymousCustomer $merge) {}

    /**
     * @param  Customer|null  $current  the customer the identity cookie points at
     * @return Customer the customer that now owns the address
     */
    public function __invoke(string $email, ?Customer $current): Customer
    {
        $address = EmailAddress::normalize($email);
        $owner = Customer::where('email', $address)->first();
        $anonymousId = $current !== null && $current->isAnonymous() ? $current->id : null;

        $plan = CustomerIdentityPlan::decide($anonymousId, $owner?->id);

        return match ($plan->action) {
            CustomerIdentityAction::CreateVerified => Customer::create([
                'email' => $address,
                'email_verified_at' => now(),
            ]),
            CustomerIdentityAction::SignInExisting => $this->verify($owner),
            CustomerIdentityAction::ClaimAnonymous => $this->claim($current, $address),
            CustomerIdentityAction::MergeAnonymousInto => ($this->merge)($current, $this->verify($owner)),
        };
    }

    /**
     * A guest checkout can leave an address on a customer without verifying
     * it; clicking a link for that address settles it.
     */
    private function verify(Customer $customer): Customer
    {
        if ($customer->email_verified_at === null) {
            $customer->forceFill(['email_verified_at' => now()])->save();
        }

        return $customer;
    }

    private function claim(Customer $anonymous, string $address): Customer
    {
        $anonymous->forceFill([
            'email' => $address,
            'email_verified_at' => now(),
        ])->save();

        return $anonymous;
    }
}
