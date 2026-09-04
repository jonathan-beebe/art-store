<?php

declare(strict_types=1);

namespace App\Domain\Customers;

it('decides the action and resulting customer for a cookie/account pair', function (
    ?string $anonymousId,
    ?string $verifiedId,
    CustomerIdentityAction $expectedAction,
    ?string $expectedResultingId,
): void {
    $plan = CustomerIdentityPlan::decide($anonymousId, $verifiedId);

    expect($plan->action)->toBe($expectedAction)
        ->and($plan->resultingCustomerId())->toBe($expectedResultingId);
})->with([
    'a visitor with no history and no account gets a new verified customer' => [null, null, CustomerIdentityAction::CreateVerified, null],
    'a visitor with no history signs in to the existing account' => [null, 'cus_00000000000000000000000002', CustomerIdentityAction::SignInExisting, 'cus_00000000000000000000000002'],
    'an anonymous visitor with no account claims the anonymous row' => ['cus_00000000000000000000000001', null, CustomerIdentityAction::ClaimAnonymous, 'cus_00000000000000000000000001'],
    'an anonymous visitor with an account merges into the account' => ['cus_00000000000000000000000001', 'cus_00000000000000000000000002', CustomerIdentityAction::MergeAnonymousInto, 'cus_00000000000000000000000002'],
    'a cookie already pointing at the account needs no merge' => ['cus_00000000000000000000000002', 'cus_00000000000000000000000002', CustomerIdentityAction::SignInExisting, 'cus_00000000000000000000000002'],
]);

it('carries both ids on a merge', function (): void {
    $plan = CustomerIdentityPlan::decide('cus_00000000000000000000000001', 'cus_00000000000000000000000002');

    expect($plan->anonymousCustomerId)->toBe('cus_00000000000000000000000001')
        ->and($plan->verifiedCustomerId)->toBe('cus_00000000000000000000000002');
});
