require "test_helper"

module Fulfillments
  class MarkShippedTest < ActiveSupport::TestCase
    test "it records the carrier and the tracking number" do
      fulfillment = ship(paid_order_for(create_verified_customer, create_listing).fulfillments.sole)

      assert_equal Domain::Orders::FulfillmentStatus::SHIPPED, fulfillment.status
      assert_equal "USPS", fulfillment.carrier
      assert_equal "9400111899", fulfillment.tracking_number
      assert_equal moment("2026-08-21 11:00:00"), fulfillment.shipped_at
    end

    test "the only shipment of an order ships the order" do
      order = paid_order_for(create_verified_customer, create_listing)

      ship(order.fulfillments.sole)

      assert_equal "shipped", order.reload.status
    end

    test "one shipment of two partially ships the order" do
      order = paid_order_for(
        create_verified_customer,
        create_listing(create_seller(shop_name: "Blue Kiln Studio")),
        create_listing(create_seller(shop_name: "Rye Press"))
      )

      ship(order.fulfillments.first)

      assert_equal "partially_shipped", order.reload.status
    end

    test "it tells the customer how to track the order" do
      buyer = create_verified_customer
      order = paid_order_for(buyer, create_listing)

      ship(order.fulfillments.sole)

      notification = Notification.where(customer_id: buyer.id).sole
      assert_equal "Order shipped", notification.subject
      assert_includes notification.body, "9400111899"
    end

    test "it refuses to ship the same fulfillment twice" do
      fulfillment = ship(paid_order_for(create_verified_customer, create_listing).fulfillments.sole)

      assert_raises(Domain::TransitionError) { ship(fulfillment) }
    end

    private

    def ship(fulfillment)
      MarkShipped.new.call(fulfillment: fulfillment, carrier: "USPS", tracking_number: "9400111899",
                           now: moment("2026-08-21 11:00:00"))
    end
  end
end
