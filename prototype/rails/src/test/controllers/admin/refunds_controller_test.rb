require "test_helper"

class Admin::RefundsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor refunds nothing" do
    fulfillment = create_fulfillment(create_seller)

    post admin_fulfillment_refund_path(fulfillment), params: refund

    assert_redirected_to admin_login_path
    assert_predicate fulfillment.reload, :awaiting_shipment?
  end

  test "an admin refunds a fulfillment and both sides are told" do
    sign_in_as_admin
    seller = create_seller
    listing = create_listing(seller, quantity: 1)
    fulfillment = create_fulfillment(seller, listing: listing)

    post admin_fulfillment_refund_path(fulfillment), params: refund

    assert_redirected_to admin_fulfillment_path(fulfillment)
    assert_predicate fulfillment.reload, :refunded?
    assert_equal "admin", fulfillment.refunds.sole.issued_by_type
    assert_equal "sold", listing.reload.status
    assert_equal "Sale refunded", seller.notifications.where(subject: "Sale refunded").sole.subject
    assert_equal "Order refunded", fulfillment.order.customer.notifications.sole.subject
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Refunded."
  end

  test "refunding the same fulfillment twice is refused" do
    sign_in_as_admin
    fulfillment = create_fulfillment(create_seller)
    post admin_fulfillment_refund_path(fulfillment), params: refund

    post admin_fulfillment_refund_path(fulfillment), params: refund

    assert_redirected_to admin_fulfillment_path(fulfillment)
    assert_equal 1, Refund.count
    follow_redirect!
    assert_select "[data-flash=alert]", text: "A fulfillment cannot move from refunded to refunded."
  end

  test "refunding a fulfillment on an unpaid order is refused" do
    sign_in_as_admin
    fulfillment = order_for(create_verified_customer, create_listing).fulfillments.sole

    post admin_fulfillment_refund_path(fulfillment), params: refund

    assert_equal 0, Refund.count
    follow_redirect!
    assert_select "[data-flash=alert]", text: Fulfillment::UNCHARGED
  end

  test "a refund with no reason is refused" do
    sign_in_as_admin
    fulfillment = create_fulfillment(create_seller)

    post admin_fulfillment_refund_path(fulfillment), params: refund(reason: " ")

    assert_equal 0, Refund.count
    follow_redirect!
    assert_select "[data-flash=alert]", text: Refund::MISSING_REASON
  end

  test "a fulfillment id nothing was written for is not found" do
    sign_in_as_admin

    post admin_fulfillment_refund_path(fulfillment_id: unused_id(:ful)), params: refund

    assert_response :not_found
  end

  test "the fulfillment page carries the form and then the refund it wrote" do
    sign_in_as_admin
    fulfillment = create_fulfillment(create_seller)

    get admin_fulfillment_path(fulfillment)
    assert_select "input[type=submit][value=?]", "Refund"
    assert_select "[data-empty=fulfillment_refunds]"

    post admin_fulfillment_refund_path(fulfillment), params: refund

    get admin_fulfillment_path(fulfillment)
    assert_select "input[type=submit][value=?]", "Refund", count: 0
    assert_select "[data-refund] [data-cell=reason]", text: "Dispute found for the buyer."
    assert_select "[data-refund] [data-cell=issued_by]", text: "Admin"
  end

  test "the order page lists the refund and what is still open to refund" do
    sign_in_as_admin
    order = create_paid_order(create_listing)
    fulfillment = order.fulfillments.sole

    get admin_order_path(order)
    assert_select "[data-fulfillment=?] [data-cell=refund]", fulfillment.id, text: "Refund"

    post admin_fulfillment_refund_path(fulfillment), params: refund

    get admin_order_path(order)
    assert_select "[data-refund] [data-cell=amount]", text: "$450.00"
    assert_select "[data-field=refunded]", text: "$450.00"
    assert_select "[data-fulfillment=?] [data-cell=refund]", fulfillment.id, count: 0
  end

  test "the lists filter on the statuses a reversal leaves behind" do
    sign_in_as_admin
    fulfillment = create_fulfillment(create_seller)
    post admin_fulfillment_refund_path(fulfillment), params: refund

    get admin_fulfillments_path(status: "refunded")
    assert_select "[data-fulfillment=?]", fulfillment.id

    get admin_orders_path(status: "refunded")
    assert_select "[data-order=?]", fulfillment.order_id
  end

  private

  def refund(**overrides)
    { reason: "Dispute found for the buyer." }.merge(overrides)
  end
end
