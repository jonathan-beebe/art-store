# Runs without Rails: ruby -Iapp app/domain/customers/identity_plan_test.rb
require "minitest/autorun"
require_relative "identity_plan"

module Domain
  module Customers
    class IdentityPlanTest < Minitest::Test
      def test_a_visitor_with_no_history_and_no_account_gets_a_new_verified_customer
        plan = IdentityPlan.decide(anonymous_customer_id: nil, verified_customer_id: nil)

        assert_equal IdentityPlan::CREATE_VERIFIED, plan.action
        assert_nil plan.resulting_customer_id
      end

      def test_a_visitor_with_no_history_signs_in_to_the_existing_account
        plan = IdentityPlan.decide(anonymous_customer_id: nil, verified_customer_id: 7)

        assert_equal IdentityPlan::SIGN_IN_EXISTING, plan.action
        assert_equal 7, plan.resulting_customer_id
      end

      def test_an_anonymous_visitor_with_no_account_claims_the_anonymous_row
        plan = IdentityPlan.decide(anonymous_customer_id: 3, verified_customer_id: nil)

        assert_equal IdentityPlan::CLAIM_ANONYMOUS, plan.action
        assert_equal 3, plan.resulting_customer_id
      end

      def test_an_anonymous_visitor_with_an_account_merges_into_the_account
        plan = IdentityPlan.decide(anonymous_customer_id: 3, verified_customer_id: 7)

        assert_equal IdentityPlan::MERGE_ANONYMOUS_INTO, plan.action
        assert_equal 3, plan.anonymous_customer_id
        assert_equal 7, plan.verified_customer_id
        assert_equal 7, plan.resulting_customer_id
      end

      def test_a_cookie_already_pointing_at_the_account_needs_no_merge
        plan = IdentityPlan.decide(anonymous_customer_id: 7, verified_customer_id: 7)

        assert_equal IdentityPlan::SIGN_IN_EXISTING, plan.action
        assert_equal 7, plan.resulting_customer_id
      end
    end
  end
end
