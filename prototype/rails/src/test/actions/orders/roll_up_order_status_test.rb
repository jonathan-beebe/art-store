require "test_helper"

module Orders
  class RollUpOrderStatusTest < ActiveSupport::TestCase
    test "an order whose fulfillments all await shipment stays paid" do
      order = paid_order_for(create_verified_customer, create_listing)

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::PAID, order.reload.status
    end

    test "one shipped fulfillment of two partially ships the order" do
      order = paid_order_for(
        create_verified_customer,
        create_listing(create_seller(shop_name: "Blue Kiln Studio")),
        create_listing(create_seller(shop_name: "Rye Press"))
      )
      order.fulfillments.first.update!(status: Domain::Orders::FulfillmentStatus::SHIPPED)

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::PARTIALLY_SHIPPED, order.reload.status
    end

    test "every fulfillment delivered delivers the order" do
      order = paid_order_for(create_verified_customer, create_listing)
      order.fulfillments.sole.update!(status: Domain::Orders::FulfillmentStatus::DELIVERED)

      RollUpOrderStatus.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::DELIVERED, order.reload.status
    end
  end
end
