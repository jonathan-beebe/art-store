require "test_helper"

module Domain
  module Orders
    class PaymentAttemptTest < ActiveSupport::TestCase
      NOW = Time.utc(2026, 8, 20, 10, 0, 0)

      test "an approved card pays the order" do
        attempt = attempt_with("4242424242424242", OrderStatus::AWAITING_PAYMENT)

        assert_equal OrderStatus::PAID, attempt.order_status
        assert_predicate attempt, :paid?
        assert_equal Payments::PaymentStatus::APPROVED, attempt.payment_status
        assert_equal NOW, attempt.finalized_at
      end

      test "an approved card keeps the stock placement took" do
        assert_equal Listings::StockChange::KEEP, attempt_with("4242424242424242", OrderStatus::AWAITING_PAYMENT).stock_change
      end

      test "a declined card fails the payment and finalizes nothing" do
        attempt = attempt_with("4000000000000002", OrderStatus::AWAITING_PAYMENT)

        assert_equal OrderStatus::PAYMENT_FAILED, attempt.order_status
        refute_predicate attempt, :paid?
        assert_equal Payments::PaymentStatus::DECLINED, attempt.payment_status
        assert_equal Payments::DeclineReason::GENERIC_DECLINE, attempt.decline_reason
        assert_nil attempt.finalized_at
      end

      test "a declined card hands the stock back" do
        assert_equal Listings::StockChange::RESTORE, attempt_with("4000000000000002", OrderStatus::AWAITING_PAYMENT).stock_change
      end

      test "a retry claims the stock again" do
        assert_equal Listings::StockChange::TAKE, attempt_with("4242424242424242", OrderStatus::PAYMENT_FAILED).stock_change
      end

      test "it keeps only the last four digits" do
        assert_equal "4242", attempt_with("4242 4242 4242 4242", OrderStatus::AWAITING_PAYMENT).card_last_four
      end

      test "it refuses to charge an order that cannot be paid" do
        assert_raises(TransitionError) { attempt_with("4242424242424242", OrderStatus::PAID) }
      end

      test "a paid attempt settles every fulfillment" do
        attempt = attempt_with("4242424242424242", OrderStatus::AWAITING_PAYMENT)

        assert_equal %i[first second], attempt.settled(%i[first second])
      end

      test "a failed attempt settles nothing" do
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
