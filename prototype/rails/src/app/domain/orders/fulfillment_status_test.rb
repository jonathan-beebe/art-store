# Runs without Rails: ruby -Iapp app/domain/orders/fulfillment_status_test.rb
require "minitest/autorun"
require_relative "fulfillment_status"

module Domain
  module Orders
    class FulfillmentStatusTest < Minitest::Test
      def test_all_names_every_status
        assert_equal %w[awaiting_shipment shipped delivered], FulfillmentStatus::ALL
      end

      def test_a_fulfillment_awaiting_shipment_ships
        assert FulfillmentStatus.can_transition?(FulfillmentStatus::AWAITING_SHIPMENT, FulfillmentStatus::SHIPPED)
      end

      def test_a_shipped_fulfillment_is_delivered
        assert FulfillmentStatus.can_transition?(FulfillmentStatus::SHIPPED, FulfillmentStatus::DELIVERED)
      end

      def test_a_fulfillment_cannot_skip_shipping
        refute FulfillmentStatus.can_transition?(FulfillmentStatus::AWAITING_SHIPMENT, FulfillmentStatus::DELIVERED)
      end

      def test_a_delivered_fulfillment_goes_nowhere
        assert_empty FulfillmentStatus::TRANSITIONS.fetch(FulfillmentStatus::DELIVERED)
      end

      def test_transition_returns_the_next_status
        assert_equal FulfillmentStatus::SHIPPED,
                     FulfillmentStatus.transition(FulfillmentStatus::AWAITING_SHIPMENT, FulfillmentStatus::SHIPPED)
      end

      def test_transition_refuses_a_second_delivery
        error = assert_raises(TransitionError) do
          FulfillmentStatus.transition(FulfillmentStatus::DELIVERED, FulfillmentStatus::DELIVERED)
        end
        assert_equal "A fulfillment cannot move from delivered to delivered.", error.message
      end

      def test_a_shipped_or_delivered_fulfillment_has_departed
        assert FulfillmentStatus.departed?(FulfillmentStatus::SHIPPED)
        assert FulfillmentStatus.departed?(FulfillmentStatus::DELIVERED)
      end

      def test_a_fulfillment_awaiting_shipment_has_not_departed
        refute FulfillmentStatus.departed?(FulfillmentStatus::AWAITING_SHIPMENT)
      end
    end
  end
end
