# Runs without Rails: ruby -Iapp app/domain/payments/card_decision_test.rb
require "minitest/autorun"
require_relative "card_decision"

module Domain
  module Payments
    class CardDecisionTest < Minitest::Test
      def test_an_approved_decision_carries_no_reason
        decision = CardDecision.approved("4242")

        assert_predicate decision, :approved?
        assert_nil decision.decline_reason
        assert_equal "4242", decision.last_four
      end

      def test_a_declined_decision_carries_the_reason
        decision = CardDecision.declined("0002", "generic_decline")

        refute_predicate decision, :approved?
        assert_equal "generic_decline", decision.decline_reason
      end
    end
  end
end
