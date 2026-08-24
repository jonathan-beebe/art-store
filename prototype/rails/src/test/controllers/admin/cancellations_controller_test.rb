require "test_helper"

class Admin::CancellationsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor cancels nothing" do
    order = unpaid_order

    post admin_order_cancellation_path(order)

    assert_redirected_to admin_login_path
    assert_predicate order.reload, :awaiting_payment?
  end

  test "an admin cancels an unpaid order and both sides are told" do
    sign_in_as_admin
    listing = create_listing(quantity: 1)
    order = unpaid_order(listing)

    post admin_order_cancellation_path(order)

    assert_redirected_to admin_order_path(order)
    assert_predicate order.reload, :cancelled?
    assert_equal "for_sale", listing.reload.status
    assert_equal "Order cancelled", order.customer.notifications.sole.subject
    assert_equal "Order cancelled", listing.seller.notifications.sole.subject
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Order cancelled."
  end

  test "cancelling a paid order is refused" do
    sign_in_as_admin
    order = create_paid_order(create_listing)

    post admin_order_cancellation_path(order)

    assert_redirected_to admin_order_path(order)
    assert_predicate order.reload, :paid?
    follow_redirect!
    assert_select "[data-flash=alert]", text: "An order cannot move from paid to cancelled."
  end

  test "an order id nothing was written for is not found" do
    sign_in_as_admin

    post admin_order_cancellation_path(order_id: unused_id(:ord))

    assert_response :not_found
  end

  test "the order page offers the button only while the order can still be called off" do
    sign_in_as_admin
    order = unpaid_order

    get admin_order_path(order)
    assert_select "[data-field=status] button", text: "Cancel"

    order.cancel!(by: create_admin)

    get admin_order_path(order)
    assert_select "[data-field=status] button", text: "Cancel", count: 0
  end

  private

  def unpaid_order(listing = create_listing)
    order_for(create_verified_customer, listing)
  end
end
