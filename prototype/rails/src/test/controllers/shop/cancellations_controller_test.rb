require "test_helper"

module Shop
  class CancellationsControllerTest < ActionDispatch::IntegrationTest
    test "a visitor with no orders cancels nothing" do
      order = unpaid_order
      end_session
      sign_in_as_customer(email: "stranger@example.com")

      post shop_cancel_order_path(order)

      assert_response :not_found
      assert_predicate order.reload, :awaiting_payment?
    end

    test "cancelling an unpaid order hands the stock back" do
      listing = create_listing(title: "Harbour at Dusk", quantity: 1)
      order = unpaid_order(listing)

      post shop_cancel_order_path(order)

      assert_redirected_to shop_order_path(order)
      assert_predicate order.reload, :cancelled?
      assert_equal "for_sale", listing.reload.status
      follow_redirect!
      assert_select "[data-flash=notice]", text: "Order cancelled."
      assert_select "[data-order-status]", text: "Cancelled"
    end

    test "cancelling a paid order is refused" do
      order = paid_order

      post shop_cancel_order_path(order)

      assert_redirected_to shop_order_path(order)
      assert_predicate order.reload, :paid?
      follow_redirect!
      assert_select "[data-flash=alert]", text: "An order cannot move from paid to cancelled."
    end

    test "cancelling a paid order logs the refusal at info" do
      order = paid_order

      lines = captured_log_lines { post shop_cancel_order_path(order) }

      refusal = log_lines_for("order.cancel", lines).last
      assert_equal "refused", refusal["phase"]
      assert_equal "info", refusal["level"]
    end

    test "cancelling another customer's order is not found" do
      order = paid_order
      end_session
      sign_in_as_customer(email: "stranger@example.com")

      post shop_cancel_order_path(order)

      assert_response :not_found
      assert_predicate order.reload, :paid?
    end

    test "the order page offers the button only while the order can still be called off" do
      order = unpaid_order

      get shop_order_path(order)
      assert_select "button", text: "Cancel this order"

      order.cancel!(by: order.customer)

      get shop_order_path(order)
      assert_select "button", text: "Cancel this order", count: 0
    end

    test "the order page names what went back and why" do
      order = paid_order
      fulfillment = order.fulfillments.sole
      fulfillment.decline!(reason: "The kiln cracked it.", by: fulfillment.seller)

      get shop_order_path(order)

      assert_select "[data-order-refunded]", text: "$450.00 refunded"
      assert_select "[data-fulfillment-status]", text: "Declined"
      assert_select "[data-refund-reason]", text: "The kiln cracked it."
    end

    private

    def unpaid_order(listing = create_listing(title: "Harbour at Dusk"))
      sign_in_as_customer(email: "buyer@example.com")

      order_for(visiting_customer, listing)
    end

    def paid_order(listing = create_listing(title: "Harbour at Dusk"))
      unpaid_order(listing).pay!(APPROVED_CARD, at: moment("2026-08-20 10:00:00"))
    end
  end
end
