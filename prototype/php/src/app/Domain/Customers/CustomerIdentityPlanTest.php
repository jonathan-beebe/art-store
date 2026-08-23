<?php

declare(strict_types=1);

namespace App\Domain\Customers;

it('decides the action and resulting customer for a cookie/account pair', function (
    ?int $anonymousId,
    ?int $verifiedId,
    CustomerIdentityAction $expectedAction,
    ?int $expectedResultingId,
): void {
    $plan = CustomerIdentityPlan::decide($anonymousId, $verifiedId);

    expect($plan->action)->toBe($expectedAction)
        ->and($plan->resultingCustomerId())->toBe($expectedResultingId);
})->with([
    'a visitor with no history and no account gets a new verified customer' => [null, null, CustomerIdentityAction::CreateVerified, null],
    'a visitor with no history signs in to the existing account' => [null, 7, CustomerIdentityAction::SignInExisting, 7],
    'an anonymous visitor with no account claims the anonymous row' => [3, null, CustomerIdentityAction::ClaimAnonymous, 3],
    'an anonymous visitor with an account merges into the account' => [3, 7, CustomerIdentityAction::MergeAnonymousInto, 7],
    'a cookie already pointing at the account needs no merge' => [7, 7, CustomerIdentityAction::SignInExisting, 7],
]);

it('carries both ids on a merge', function (): void {
    $plan = CustomerIdentityPlan::decide(3, 7);

    expect($plan->anonymousCustomerId)->toBe(3)
        ->and($plan->verifiedCustomerId)->toBe(7);
});
