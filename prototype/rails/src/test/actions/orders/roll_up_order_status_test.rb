require "commerce_test_case"

module Orders
  class RollUpOrderStatusTest < CommerceTestCase
    def test_an_order_whose_fulfillments_all_await_shipment_stays_paid
      order = paid_order_for(customer, listing(seller))

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::PAID, order.reload.status
    end

    def test_one_shipped_fulfillment_of_two_partially_ships_the_order
      order = paid_order_for(customer, listing(seller("Blue Kiln Studio")), listing(seller("Rye Press")))
      order.fulfillments.first.update!(status: Domain::Orders::FulfillmentStatus::SHIPPED)

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::PARTIALLY_SHIPPED, order.reload.status
    end

    def test_every_fulfillment_delivered_delivers_the_order
      order = paid_order_for(customer, listing(seller))
      order.fulfillments.sole.update!(status: Domain::Orders::FulfillmentStatus::DELIVERED)

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::DELIVERED, order.reload.status
    end
  end
end
