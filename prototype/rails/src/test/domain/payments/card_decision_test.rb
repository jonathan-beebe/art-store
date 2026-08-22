require "test_helper"

module Domain
  module Payments
    class CardDecisionTest < ActiveSupport::TestCase
      test "an approved decision carries no reason" do
        decision = CardDecision.approved("4242")

        assert_predicate decision, :approved?
        assert_nil decision.decline_reason
        assert_equal "4242", decision.last_four
      end

      test "a declined decision carries the reason" do
        decision = CardDecision.declined("0002", "generic_decline")

        refute_predicate decision, :approved?
        assert_equal "generic_decline", decision.decline_reason
      end
    end
  end
end
