require "commerce_test_case"

module Orders
  class RollUpOrderStatusTest < CommerceTestCase
    test "an order whose fulfillments all await shipment stays paid" do
      order = paid_order_for(customer, listing(seller))

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::PAID, order.reload.status
    end

    test "one shipped fulfillment of two partially ships the order" do
      order = paid_order_for(customer, listing(seller("Blue Kiln Studio")), listing(seller("Rye Press")))
      order.fulfillments.first.update!(status: Domain::Orders::FulfillmentStatus::SHIPPED)

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::PARTIALLY_SHIPPED, order.reload.status
    end

    test "every fulfillment delivered delivers the order" do
      order = paid_order_for(customer, listing(seller))
      order.fulfillments.sole.update!(status: Domain::Orders::FulfillmentStatus::DELIVERED)

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::DELIVERED, order.reload.status
    end
  end
end
