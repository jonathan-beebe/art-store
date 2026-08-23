require "test_helper"

module Orders
  class MarkAwaitingPaymentTest < ActiveSupport::TestCase
    test "verifying opens payment on a guest order" do
      order = order_for(create_anonymous_customer, create_listing)

      MarkAwaitingPayment.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.reload.status
    end

    test "an order that already awaits payment stays where it is" do
      order = order_for(create_verified_customer, create_listing)

      MarkAwaitingPayment.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.reload.status
    end
  end
end
