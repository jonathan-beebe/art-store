require "test_helper"

module Domain
  module Payments
    class PaymentStatusTest < ActiveSupport::TestCase
      test "all names every status" do
        assert_equal %w[approved declined], PaymentStatus::ALL
      end

      test "an approved card records an approved payment" do
        assert_equal PaymentStatus::APPROVED, PaymentStatus.from_card_decision(CardDecision.approved("4242"))
      end

      test "a declined card records a declined payment" do
        decision = CardDecision.declined("0002", DeclineReason::GENERIC_DECLINE)

        assert_equal PaymentStatus::DECLINED, PaymentStatus.from_card_decision(decision)
      end
    end
  end
end
