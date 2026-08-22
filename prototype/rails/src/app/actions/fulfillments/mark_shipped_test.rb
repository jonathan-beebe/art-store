require "commerce_test_case"

module Fulfillments
  class MarkShippedTest < CommerceTestCase
    def test_it_records_the_carrier_and_the_tracking_number
      fulfillment = ship(paid_order_for(customer, listing(seller)).fulfillments.sole)

      assert_equal Domain::Orders::FulfillmentStatus::SHIPPED, fulfillment.status
      assert_equal "USPS", fulfillment.carrier
      assert_equal "9400111899", fulfillment.tracking_number
      assert_equal moment("2026-08-21 11:00:00"), fulfillment.shipped_at
    end

    def test_the_only_shipment_of_an_order_ships_the_order
      order = paid_order_for(customer, listing(seller))

      ship(order.fulfillments.sole)

      assert_equal Domain::Orders::OrderStatus::SHIPPED, order.reload.status
    end

    def test_one_shipment_of_two_partially_ships_the_order
      order = paid_order_for(customer, listing(seller("Blue Kiln Studio")), listing(seller("Rye Press")))

      ship(order.fulfillments.first)

      assert_equal Domain::Orders::OrderStatus::PARTIALLY_SHIPPED, order.reload.status
    end

    def test_it_tells_the_customer_how_to_track_the_order
      buyer = customer
      order = paid_order_for(buyer, listing(seller))

      ship(order.fulfillments.sole)

      notification = Notification.where(customer_id: buyer.id).sole
      assert_equal "Order shipped", notification.subject
      assert_includes notification.body, "9400111899"
    end

    def test_it_refuses_to_ship_the_same_fulfillment_twice
      fulfillment = ship(paid_order_for(customer, listing(seller)).fulfillments.sole)

      assert_raises(Domain::TransitionError) { ship(fulfillment) }
    end

    private

    def ship(fulfillment)
      MarkShipped.new.call(fulfillment: fulfillment, carrier: "USPS", tracking_number: "9400111899",
                           now: moment("2026-08-21 11:00:00"))
    end
  end
end
