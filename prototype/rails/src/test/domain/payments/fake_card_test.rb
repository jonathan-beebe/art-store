require "test_helper"

module Domain
  module Payments
    class FakeCardTest < ActiveSupport::TestCase
      test "the approved number is approved" do
        assert_predicate FakeCard.decide("4242424242424242"), :approved?
      end

      test "spaces and dashes are ignored" do
        assert_predicate FakeCard.decide("4242 4242-4242 4242"), :approved?
      end

      test "the generic decline number is declined" do
        decision = FakeCard.decide("4000 0000 0000 0002")

        refute_predicate decision, :approved?
        assert_equal DeclineReason::GENERIC_DECLINE, decision.decline_reason
      end

      test "the insufficient funds number is declined" do
        assert_equal DeclineReason::INSUFFICIENT_FUNDS, FakeCard.decide("4000 0000 0000 9995").decline_reason
      end

      test "any other number is not a valid card" do
        assert_equal DeclineReason::INVALID_CARD_NUMBER, FakeCard.decide("1234 5678 1234 5678").decline_reason
      end

      test "only the last four digits come back" do
        assert_equal "9995", FakeCard.decide("4000 0000 0000 9995").last_four
      end

      test "a number shorter than four digits keeps what it has" do
        assert_equal "12", FakeCard.decide("12").last_four
      end
    end
  end
end
