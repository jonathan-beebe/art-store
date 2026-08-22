require "test_helper"

module Domain
  module Orders
    class OrderStatusTest < ActiveSupport::TestCase
      test "every status has a transition list" do
        assert_equal OrderStatus::ALL.sort, OrderStatus::TRANSITIONS.keys.sort
      end

      test "verifying an email opens payment" do
        assert OrderStatus.can_transition?(OrderStatus::PENDING_VERIFICATION, OrderStatus::AWAITING_PAYMENT)
      end

      test "an order awaiting payment is paid or fails" do
        assert OrderStatus.can_transition?(OrderStatus::AWAITING_PAYMENT, OrderStatus::PAID)
        assert OrderStatus.can_transition?(OrderStatus::AWAITING_PAYMENT, OrderStatus::PAYMENT_FAILED)
      end

      test "a guest cannot pay before verifying" do
        refute OrderStatus.can_transition?(OrderStatus::PENDING_VERIFICATION, OrderStatus::PAID)
      end

      test "a failed payment retries" do
        assert OrderStatus.can_transition?(OrderStatus::PAYMENT_FAILED, OrderStatus::PAID)
      end

      test "a retry that is declined again stays where it was" do
        assert OrderStatus.can_transition?(OrderStatus::PAYMENT_FAILED, OrderStatus::PAYMENT_FAILED)
      end

      test "a paid order ships whole or in part" do
        assert OrderStatus.can_transition?(OrderStatus::PAID, OrderStatus::SHIPPED)
        assert OrderStatus.can_transition?(OrderStatus::PAID, OrderStatus::PARTIALLY_SHIPPED)
      end

      test "a delivered order goes nowhere" do
        assert_empty OrderStatus::TRANSITIONS.fetch(OrderStatus::DELIVERED)
      end

      test "a cancelled order goes nowhere" do
        assert_empty OrderStatus::TRANSITIONS.fetch(OrderStatus::CANCELLED)
      end

      test "a paid order cannot be paid twice" do
        error = assert_raises(TransitionError) { OrderStatus.transition(OrderStatus::PAID, OrderStatus::PAID) }
        assert_equal "An order cannot move from paid to paid.", error.message
      end

      test "a verified purchaser places an order that awaits payment" do
        assert_equal OrderStatus::AWAITING_PAYMENT, OrderStatus.for_placement(verified_purchaser)
      end

      test "a guest places an order that awaits verification" do
        assert_equal OrderStatus::PENDING_VERIFICATION, OrderStatus.for_placement(guest_purchaser)
      end

      test "verification moves an order that was waiting on it" do
        assert_equal OrderStatus::AWAITING_PAYMENT, OrderStatus.after_verification(OrderStatus::PENDING_VERIFICATION)
      end

      test "verification leaves every other status alone" do
        assert_equal OrderStatus::PAID, OrderStatus.after_verification(OrderStatus::PAID)
        assert_equal OrderStatus::AWAITING_PAYMENT, OrderStatus.after_verification(OrderStatus::AWAITING_PAYMENT)
      end

      test "an approved card pays the order" do
        assert_equal OrderStatus::PAID, OrderStatus.from_card_decision(Payments::CardDecision.approved("4242"))
      end

      test "a declined card fails the payment" do
        decision = Payments::CardDecision.declined("0002", "generic_decline")

        assert_equal OrderStatus::PAYMENT_FAILED, OrderStatus.from_card_decision(decision)
      end

      test "an order whose fulfillments all delivered is delivered" do
        assert_equal OrderStatus::DELIVERED, OrderStatus.from_fulfillments(%w[delivered delivered])
      end

      test "an order whose fulfillments all departed is shipped" do
        assert_equal OrderStatus::SHIPPED, OrderStatus.from_fulfillments(%w[shipped delivered])
      end

      test "an order with one fulfillment still in the studio is partially shipped" do
        assert_equal OrderStatus::PARTIALLY_SHIPPED, OrderStatus.from_fulfillments(%w[delivered awaiting_shipment])
      end

      test "an order whose fulfillments all await shipment is paid" do
        assert_equal OrderStatus::PAID, OrderStatus.from_fulfillments(%w[awaiting_shipment awaiting_shipment])
      end

      test "an order rolls up from at least one fulfillment" do
        assert_raises(ArgumentError) { OrderStatus.from_fulfillments([]) }
      end

      private

      def verified_purchaser
        Purchaser.new(id: 1, email: "ada@example.test", email_verified_at: Time.utc(2026, 8, 19))
      end

      def guest_purchaser
        Purchaser.new(id: 2, email: "guest@example.test", email_verified_at: nil)
      end
    end
  end
end
