<?php

namespace App\Domain\Customers;

use PHPUnit\Framework\TestCase;

final class CustomerIdentityPlanTest extends TestCase
{
    public function test_a_visitor_with_no_history_and_no_account_gets_a_new_verified_customer(): void
    {
        $plan = CustomerIdentityPlan::decide(null, null);

        $this->assertSame(CustomerIdentityAction::CreateVerified, $plan->action);
        $this->assertNull($plan->resultingCustomerId());
    }

    public function test_a_visitor_with_no_history_signs_in_to_the_existing_account(): void
    {
        $plan = CustomerIdentityPlan::decide(null, 7);

        $this->assertSame(CustomerIdentityAction::SignInExisting, $plan->action);
        $this->assertSame(7, $plan->resultingCustomerId());
    }

    public function test_an_anonymous_visitor_with_no_account_claims_the_anonymous_row(): void
    {
        $plan = CustomerIdentityPlan::decide(3, null);

        $this->assertSame(CustomerIdentityAction::ClaimAnonymous, $plan->action);
        $this->assertSame(3, $plan->resultingCustomerId());
    }

    public function test_an_anonymous_visitor_with_an_account_merges_into_the_account(): void
    {
        $plan = CustomerIdentityPlan::decide(3, 7);

        $this->assertSame(CustomerIdentityAction::MergeAnonymousInto, $plan->action);
        $this->assertSame(3, $plan->anonymousCustomerId);
        $this->assertSame(7, $plan->verifiedCustomerId);
        $this->assertSame(7, $plan->resultingCustomerId());
    }

    public function test_a_cookie_already_pointing_at_the_account_needs_no_merge(): void
    {
        $plan = CustomerIdentityPlan::decide(7, 7);

        $this->assertSame(CustomerIdentityAction::SignInExisting, $plan->action);
        $this->assertSame(7, $plan->resultingCustomerId());
    }
}
