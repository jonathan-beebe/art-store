# Runs without Rails: ruby -Iapp app/domain/orders/payment_attempt_test.rb
require "minitest/autorun"
require_relative "payment_attempt"
require_relative "../payments/fake_card"

module Domain
  module Orders
    class PaymentAttemptTest < Minitest::Test
      NOW = Time.utc(2026, 8, 20, 10, 0, 0)

      def test_an_approved_card_pays_the_order
        attempt = attempt_with("4242424242424242", OrderStatus::AWAITING_PAYMENT)

        assert_equal OrderStatus::PAID, attempt.order_status
        assert_predicate attempt, :paid?
        assert_equal Payments::PaymentStatus::APPROVED, attempt.payment_status
        assert_equal NOW, attempt.finalized_at
      end

      def test_an_approved_card_keeps_the_stock_placement_took
        assert_equal Listings::StockChange::KEEP, attempt_with("4242424242424242", OrderStatus::AWAITING_PAYMENT).stock_change
      end

      def test_a_declined_card_fails_the_payment_and_finalizes_nothing
        attempt = attempt_with("4000000000000002", OrderStatus::AWAITING_PAYMENT)

        assert_equal OrderStatus::PAYMENT_FAILED, attempt.order_status
        refute_predicate attempt, :paid?
        assert_equal Payments::PaymentStatus::DECLINED, attempt.payment_status
        assert_equal Payments::DeclineReason::GENERIC_DECLINE, attempt.decline_reason
        assert_nil attempt.finalized_at
      end

      def test_a_declined_card_hands_the_stock_back
        assert_equal Listings::StockChange::RESTORE, attempt_with("4000000000000002", OrderStatus::AWAITING_PAYMENT).stock_change
      end

      def test_a_retry_claims_the_stock_again
        assert_equal Listings::StockChange::TAKE, attempt_with("4242424242424242", OrderStatus::PAYMENT_FAILED).stock_change
      end

      def test_it_keeps_only_the_last_four_digits
        assert_equal "4242", attempt_with("4242 4242 4242 4242", OrderStatus::AWAITING_PAYMENT).card_last_four
      end

      def test_it_refuses_to_charge_an_order_that_cannot_be_paid
        assert_raises(TransitionError) { attempt_with("4242424242424242", OrderStatus::PAID) }
      end

      def test_a_paid_attempt_settles_every_fulfillment
        attempt = attempt_with("4242424242424242", OrderStatus::AWAITING_PAYMENT)

        assert_equal %i[first second], attempt.settled(%i[first second])
      end

      def test_a_failed_attempt_settles_nothing
        attempt = attempt_with("4000000000000002", OrderStatus::AWAITING_PAYMENT)

        assert_empty attempt.settled(%i[first second])
      end

      private

      def attempt_with(card_number, status)
        PaymentAttempt.from(status: status, decision: Payments::FakeCard.decide(card_number), now: NOW)
      end
    end
  end
end
