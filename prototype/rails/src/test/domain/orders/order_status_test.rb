require "test_helper"

module Domain
  module Orders
    class OrderStatusTest < ActiveSupport::TestCase
      def test_every_status_has_a_transition_list
        assert_equal OrderStatus::ALL.sort, OrderStatus::TRANSITIONS.keys.sort
      end

      def test_verifying_an_email_opens_payment
        assert OrderStatus.can_transition?(OrderStatus::PENDING_VERIFICATION, OrderStatus::AWAITING_PAYMENT)
      end

      def test_an_order_awaiting_payment_is_paid_or_fails
        assert OrderStatus.can_transition?(OrderStatus::AWAITING_PAYMENT, OrderStatus::PAID)
        assert OrderStatus.can_transition?(OrderStatus::AWAITING_PAYMENT, OrderStatus::PAYMENT_FAILED)
      end

      def test_a_guest_cannot_pay_before_verifying
        refute OrderStatus.can_transition?(OrderStatus::PENDING_VERIFICATION, OrderStatus::PAID)
      end

      def test_a_failed_payment_retries
        assert OrderStatus.can_transition?(OrderStatus::PAYMENT_FAILED, OrderStatus::PAID)
      end

      def test_a_retry_that_is_declined_again_stays_where_it_was
        assert OrderStatus.can_transition?(OrderStatus::PAYMENT_FAILED, OrderStatus::PAYMENT_FAILED)
      end

      def test_a_paid_order_ships_whole_or_in_part
        assert OrderStatus.can_transition?(OrderStatus::PAID, OrderStatus::SHIPPED)
        assert OrderStatus.can_transition?(OrderStatus::PAID, OrderStatus::PARTIALLY_SHIPPED)
      end

      def test_a_delivered_order_goes_nowhere
        assert_empty OrderStatus::TRANSITIONS.fetch(OrderStatus::DELIVERED)
      end

      def test_a_cancelled_order_goes_nowhere
        assert_empty OrderStatus::TRANSITIONS.fetch(OrderStatus::CANCELLED)
      end

      def test_a_paid_order_cannot_be_paid_twice
        error = assert_raises(TransitionError) { OrderStatus.transition(OrderStatus::PAID, OrderStatus::PAID) }
        assert_equal "An order cannot move from paid to paid.", error.message
      end

      def test_a_verified_purchaser_places_an_order_that_awaits_payment
        assert_equal OrderStatus::AWAITING_PAYMENT, OrderStatus.for_placement(verified_purchaser)
      end

      def test_a_guest_places_an_order_that_awaits_verification
        assert_equal OrderStatus::PENDING_VERIFICATION, OrderStatus.for_placement(guest_purchaser)
      end

      def test_verification_moves_an_order_that_was_waiting_on_it
        assert_equal OrderStatus::AWAITING_PAYMENT, OrderStatus.after_verification(OrderStatus::PENDING_VERIFICATION)
      end

      def test_verification_leaves_every_other_status_alone
        assert_equal OrderStatus::PAID, OrderStatus.after_verification(OrderStatus::PAID)
        assert_equal OrderStatus::AWAITING_PAYMENT, OrderStatus.after_verification(OrderStatus::AWAITING_PAYMENT)
      end

      def test_an_approved_card_pays_the_order
        assert_equal OrderStatus::PAID, OrderStatus.from_card_decision(Payments::CardDecision.approved("4242"))
      end

      def test_a_declined_card_fails_the_payment
        decision = Payments::CardDecision.declined("0002", "generic_decline")

        assert_equal OrderStatus::PAYMENT_FAILED, OrderStatus.from_card_decision(decision)
      end

      def test_an_order_whose_fulfillments_all_delivered_is_delivered
        assert_equal OrderStatus::DELIVERED, OrderStatus.from_fulfillments(%w[delivered delivered])
      end

      def test_an_order_whose_fulfillments_all_departed_is_shipped
        assert_equal OrderStatus::SHIPPED, OrderStatus.from_fulfillments(%w[shipped delivered])
      end

      def test_an_order_with_one_fulfillment_still_in_the_studio_is_partially_shipped
        assert_equal OrderStatus::PARTIALLY_SHIPPED, OrderStatus.from_fulfillments(%w[delivered awaiting_shipment])
      end

      def test_an_order_whose_fulfillments_all_await_shipment_is_paid
        assert_equal OrderStatus::PAID, OrderStatus.from_fulfillments(%w[awaiting_shipment awaiting_shipment])
      end

      def test_an_order_rolls_up_from_at_least_one_fulfillment
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
