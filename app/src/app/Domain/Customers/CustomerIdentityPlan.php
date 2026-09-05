<?php

declare(strict_types=1);

namespace App\Domain\Customers;

final readonly class CustomerIdentityPlan
{
    private function __construct(
        public CustomerIdentityAction $action,
        public ?string $anonymousCustomerId,
        public ?string $verifiedCustomerId,
    ) {}

    /**
     * @param  string|null  $anonymousCustomerId  the customer the cookie points at, when that row has no email yet
     * @param  string|null  $verifiedCustomerId  the customer already holding the address being verified
     */
    public static function decide(?string $anonymousCustomerId, ?string $verifiedCustomerId): self
    {
        if ($anonymousCustomerId === null) {
            $action = $verifiedCustomerId === null
                ? CustomerIdentityAction::CreateVerified
                : CustomerIdentityAction::SignInExisting;

            return new self($action, null, $verifiedCustomerId);
        }

        if ($verifiedCustomerId === null) {
            return new self(CustomerIdentityAction::ClaimAnonymous, $anonymousCustomerId, null);
        }

        if ($anonymousCustomerId === $verifiedCustomerId) {
            return new self(CustomerIdentityAction::SignInExisting, null, $verifiedCustomerId);
        }

        return new self(CustomerIdentityAction::MergeAnonymousInto, $anonymousCustomerId, $verifiedCustomerId);
    }

    /**
     * The customer the cookie and the guard end on, or null when the row does
     * not exist yet.
     */
    public function resultingCustomerId(): ?string
    {
        return match ($this->action) {
            CustomerIdentityAction::CreateVerified => null,
            CustomerIdentityAction::ClaimAnonymous => $this->anonymousCustomerId,
            CustomerIdentityAction::SignInExisting,
            CustomerIdentityAction::MergeAnonymousInto => $this->verifiedCustomerId,
        };
    }
}
