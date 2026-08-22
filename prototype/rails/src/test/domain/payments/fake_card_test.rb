require "test_helper"

module Domain
  module Payments
    class FakeCardTest < ActiveSupport::TestCase
      def test_the_approved_number_is_approved
        assert_predicate FakeCard.decide("4242424242424242"), :approved?
      end

      def test_spaces_and_dashes_are_ignored
        assert_predicate FakeCard.decide("4242 4242-4242 4242"), :approved?
      end

      def test_the_generic_decline_number_is_declined
        decision = FakeCard.decide("4000 0000 0000 0002")

        refute_predicate decision, :approved?
        assert_equal DeclineReason::GENERIC_DECLINE, decision.decline_reason
      end

      def test_the_insufficient_funds_number_is_declined
        assert_equal DeclineReason::INSUFFICIENT_FUNDS, FakeCard.decide("4000 0000 0000 9995").decline_reason
      end

      def test_any_other_number_is_not_a_valid_card
        assert_equal DeclineReason::INVALID_CARD_NUMBER, FakeCard.decide("1234 5678 1234 5678").decline_reason
      end

      def test_only_the_last_four_digits_come_back
        assert_equal "9995", FakeCard.decide("4000 0000 0000 9995").last_four
      end

      def test_a_number_shorter_than_four_digits_keeps_what_it_has
        assert_equal "12", FakeCard.decide("12").last_four
      end
    end
  end
end
