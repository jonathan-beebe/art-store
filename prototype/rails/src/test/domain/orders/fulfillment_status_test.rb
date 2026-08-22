require "test_helper"

module Domain
  module Orders
    class FulfillmentStatusTest < ActiveSupport::TestCase
      test "all names every status" do
        assert_equal %w[awaiting_shipment shipped delivered], FulfillmentStatus::ALL
      end

      test "a fulfillment awaiting shipment ships" do
        assert FulfillmentStatus.can_transition?(FulfillmentStatus::AWAITING_SHIPMENT, FulfillmentStatus::SHIPPED)
      end

      test "a shipped fulfillment is delivered" do
        assert FulfillmentStatus.can_transition?(FulfillmentStatus::SHIPPED, FulfillmentStatus::DELIVERED)
      end

      test "a fulfillment cannot skip shipping" do
        refute FulfillmentStatus.can_transition?(FulfillmentStatus::AWAITING_SHIPMENT, FulfillmentStatus::DELIVERED)
      end

      test "a delivered fulfillment goes nowhere" do
        assert_empty FulfillmentStatus::TRANSITIONS.fetch(FulfillmentStatus::DELIVERED)
      end

      test "transition returns the next status" do
        assert_equal FulfillmentStatus::SHIPPED,
                     FulfillmentStatus.transition(FulfillmentStatus::AWAITING_SHIPMENT, FulfillmentStatus::SHIPPED)
      end

      test "transition refuses a second delivery" do
        error = assert_raises(TransitionError) do
          FulfillmentStatus.transition(FulfillmentStatus::DELIVERED, FulfillmentStatus::DELIVERED)
        end
        assert_equal "A fulfillment cannot move from delivered to delivered.", error.message
      end

      test "a shipped or delivered fulfillment has departed" do
        assert FulfillmentStatus.departed?(FulfillmentStatus::SHIPPED)
        assert FulfillmentStatus.departed?(FulfillmentStatus::DELIVERED)
      end

      test "a fulfillment awaiting shipment has not departed" do
        refute FulfillmentStatus.departed?(FulfillmentStatus::AWAITING_SHIPMENT)
      end
    end
  end
end
