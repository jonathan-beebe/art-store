<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\Auth\EmailNormalizer;
use App\Domain\Customers\CustomerIdentityAction;
use App\Domain\Customers\CustomerIdentityPlan;
use App\Models\Customer;
use DateTimeImmutable;
use LogicException;

final readonly class ClaimCustomerIdentity
{
    public function __construct(private MergeAnonymousCustomer $merge) {}

    /**
     * @param  Customer|null  $current  the customer the identity cookie points at
     * @return Customer the customer that now owns the address
     */
    public function __invoke(string $email, ?Customer $current, DateTimeImmutable $now): Customer
    {
        $address = EmailNormalizer::normalize($email);
        $owner = Customer::where('email', $address)->first();
        $anonymousId = $current !== null && $current->isAnonymous() ? $current->id : null;

        $plan = CustomerIdentityPlan::decide($anonymousId, $owner?->id);

        // CustomerIdentityPlan::decide() sets SignInExisting and MergeAnonymousInto
        // only when it was given a non-null verified id, and ClaimAnonymous and
        // MergeAnonymousInto only when given a non-null anonymous id, so $owner and
        // $current are the rows those ids came from.
        return match ($plan->action) {
            CustomerIdentityAction::CreateVerified => Customer::create([
                'email' => $address,
                'email_verified_at' => $now,
            ]),
            CustomerIdentityAction::SignInExisting => $this->verify($owner ?? throw new LogicException('SignInExisting requires an existing owner.'), $now),
            CustomerIdentityAction::ClaimAnonymous => $this->claim($current ?? throw new LogicException('ClaimAnonymous requires the current customer.'), $address, $now),
            CustomerIdentityAction::MergeAnonymousInto => ($this->merge)(
                $current ?? throw new LogicException('MergeAnonymousInto requires the current customer.'),
                $this->verify($owner ?? throw new LogicException('MergeAnonymousInto requires an existing owner.'), $now),
            ),
        };
    }

    /**
     * A guest checkout can leave an address on a customer without verifying
     * it; clicking a link for that address settles it.
     */
    private function verify(Customer $customer, DateTimeImmutable $now): Customer
    {
        if ($customer->email_verified_at === null) {
            $customer->forceFill(['email_verified_at' => $now])->save();
        }

        return $customer;
    }

    private function claim(Customer $anonymous, string $address, DateTimeImmutable $now): Customer
    {
        $anonymous->forceFill([
            'email' => $address,
            'email_verified_at' => $now,
        ])->save();

        return $anonymous;
    }
}
