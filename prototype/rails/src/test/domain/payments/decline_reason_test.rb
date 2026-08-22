require "test_helper"

module Domain
  module Payments
    class DeclineReasonTest < ActiveSupport::TestCase
      test "all names every reason" do
        assert_equal %w[generic_decline insufficient_funds invalid_card_number], DeclineReason::ALL
      end

      test "every reason has a message for the customer" do
        assert_equal DeclineReason::ALL.sort, DeclineReason::MESSAGES.keys.sort
      end

      test "insufficient funds says so" do
        assert_equal "Your card has insufficient funds.", DeclineReason.message(DeclineReason::INSUFFICIENT_FUNDS)
      end

      test "it refuses a reason it does not know" do
        assert_raises(KeyError) { DeclineReason.message("stolen_card") }
      end
    end
  end
end
