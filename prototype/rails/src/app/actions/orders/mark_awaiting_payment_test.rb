require "commerce_test_case"

module Orders
  class MarkAwaitingPaymentTest < CommerceTestCase
    def test_verifying_opens_payment_on_a_guest_order
      order = order_for(anonymous_customer, listing(seller))

      MarkAwaitingPayment.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.reload.status
    end

    def test_an_order_that_already_awaits_payment_stays_where_it_is
      order = order_for(customer, listing(seller))

      MarkAwaitingPayment.new.call(order: order)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.reload.status
    end
  end
end
