require "test_helper"

module Domain
  module Payments
    class PaymentStatusTest < ActiveSupport::TestCase
      def test_all_names_every_status
        assert_equal %w[approved declined], PaymentStatus::ALL
      end

      def test_an_approved_card_records_an_approved_payment
        assert_equal PaymentStatus::APPROVED, PaymentStatus.from_card_decision(CardDecision.approved("4242"))
      end

      def test_a_declined_card_records_a_declined_payment
        decision = CardDecision.declined("0002", DeclineReason::GENERIC_DECLINE)

        assert_equal PaymentStatus::DECLINED, PaymentStatus.from_card_decision(decision)
      end
    end
  end
end
