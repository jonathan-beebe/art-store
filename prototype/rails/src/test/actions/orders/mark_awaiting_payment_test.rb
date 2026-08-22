require "commerce_test_case"

module Orders
  class MarkAwaitingPaymentTest < CommerceTestCase
    test "verifying opens payment on a guest order" do
      order = order_for(anonymous_customer, listing(seller))

      MarkAwaitingPayment.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.reload.status
    end

    test "an order that already awaits payment stays where it is" do
      order = order_for(customer, listing(seller))

      MarkAwaitingPayment.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.reload.status
    end
  end
end
