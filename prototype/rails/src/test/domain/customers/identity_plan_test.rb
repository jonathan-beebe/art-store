require "test_helper"

module Domain
  module Customers
    class IdentityPlanTest < ActiveSupport::TestCase
      test "a visitor with no history and no account gets a new verified customer" do
        plan = IdentityPlan.decide(anonymous_customer_id: nil, verified_customer_id: nil)

        assert_equal IdentityPlan::CREATE_VERIFIED, plan.action
        assert_nil plan.resulting_customer_id
      end

      test "a visitor with no history signs in to the existing account" do
        plan = IdentityPlan.decide(anonymous_customer_id: nil, verified_customer_id: 7)

        assert_equal IdentityPlan::SIGN_IN_EXISTING, plan.action
        assert_equal 7, plan.resulting_customer_id
      end

      test "an anonymous visitor with no account claims the anonymous row" do
        plan = IdentityPlan.decide(anonymous_customer_id: 3, verified_customer_id: nil)

        assert_equal IdentityPlan::CLAIM_ANONYMOUS, plan.action
        assert_equal 3, plan.resulting_customer_id
      end

      test "an anonymous visitor with an account merges into the account" do
        plan = IdentityPlan.decide(anonymous_customer_id: 3, verified_customer_id: 7)

        assert_equal IdentityPlan::MERGE_ANONYMOUS_INTO, plan.action
        assert_equal 3, plan.anonymous_customer_id
        assert_equal 7, plan.verified_customer_id
        assert_equal 7, plan.resulting_customer_id
      end

      test "a cookie already pointing at the account needs no merge" do
        plan = IdentityPlan.decide(anonymous_customer_id: 7, verified_customer_id: 7)

        assert_equal IdentityPlan::SIGN_IN_EXISTING, plan.action
        assert_equal 7, plan.resulting_customer_id
      end
    end
  end
end
