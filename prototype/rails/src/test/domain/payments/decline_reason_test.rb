require "test_helper"

module Domain
  module Payments
    class DeclineReasonTest < ActiveSupport::TestCase
      def test_all_names_every_reason
        assert_equal %w[generic_decline insufficient_funds invalid_card_number], DeclineReason::ALL
      end

      def test_every_reason_has_a_message_for_the_customer
        assert_equal DeclineReason::ALL.sort, DeclineReason::MESSAGES.keys.sort
      end

      def test_insufficient_funds_says_so
        assert_equal "Your card has insufficient funds.", DeclineReason.message(DeclineReason::INSUFFICIENT_FUNDS)
      end

      def test_it_refuses_a_reason_it_does_not_know
        assert_raises(KeyError) { DeclineReason.message("stolen_card") }
      end
    end
  end
end
