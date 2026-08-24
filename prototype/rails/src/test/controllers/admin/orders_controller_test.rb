require "test_helper"

class Admin::OrdersControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_orders_path

    assert_redirected_to admin_login_path
  end

  test "the list reaches across customers" do
    sign_in_as_admin
    mine = order_for(create_verified_customer, create_listing)
    theirs = order_for(create_verified_customer, create_listing)

    get admin_orders_path

    assert_response :success
    assert_select "[data-order=?] a[href=?]", mine.id, admin_order_path(mine)
    assert_select "[data-order=?] a[href=?]", theirs.id, admin_order_path(theirs)
  end

  test "the status filter narrows to one status at a time" do
    sign_in_as_admin
    order = order_for(create_verified_customer, create_listing)
    statuses = Order.statuses.keys

    statuses.each do |status|
      order.update!(status: status)

      get admin_orders_path(status: status)
      assert_select "[data-order=?]", order.id

      get admin_orders_path(status: statuses.find { |other| other != status })
      assert_select "[data-order=?]", order.id, false
    end
  end

  test "an empty status filter keeps every status" do
    sign_in_as_admin
    order = order_for(create_verified_customer, create_listing)

    get admin_orders_path(status: "")

    assert_select "[data-order=?]", order.id
  end

  test "a status the page does not offer is a bad request" do
    sign_in_as_admin

    get admin_orders_path(status: "wat")

    assert_response :bad_request
  end

  test "the customer filter narrows to one customer's orders" do
    sign_in_as_admin
    customer = create_verified_customer
    mine = order_for(customer, create_listing)
    theirs = order_for(create_verified_customer, create_listing)

    get admin_orders_path(customer: customer.id)

    assert_select "[data-order=?]", mine.id
    assert_select "[data-order=?]", theirs.id, false
  end

  test "an empty customer filter keeps every customer" do
    sign_in_as_admin
    order = order_for(create_verified_customer, create_listing)

    get admin_orders_path(customer: "")

    assert_select "[data-order=?]", order.id
  end

  test "a customer filter carrying another table's id is a bad request" do
    sign_in_as_admin

    get admin_orders_path(customer: unused_id(:sel))

    assert_response :bad_request
  end

  test "the list says so where nothing matches" do
    sign_in_as_admin

    get admin_orders_path

    assert_select "[data-empty=?]", "orders"
  end

  test "the list costs the same however many orders it holds" do
    sign_in_as_admin
    customer = create_verified_customer
    order_for(customer, create_listing)
    one = count_queries { get admin_orders_path }

    4.times { order_for(customer, create_listing) }
    five = count_queries { get admin_orders_path }

    assert_equal one, five
  end

  test "the page reads the items, payments, and fulfillments behind an order" do
    sign_in_as_admin
    listing = create_listing(create_seller, title: "Harbour at Dusk")
    order = create_paid_order(listing)

    get admin_order_path(order)

    assert_response :success
    assert_select "h1", text: "Order #{order.id}"
    assert_select "[data-field=?]", "status", text: "Paid"
    assert_select "[data-item] a", text: "Harbour at Dusk"
    assert_select "[data-payment=?] [data-cell=?]", order.payments.sole.id, "status", text: "Approved"
    assert_select "[data-fulfillment=?]", order.fulfillments.sole.id
  end

  test "a declined card reads its reason on the order" do
    sign_in_as_admin
    order = order_for(create_verified_customer, create_listing)
    order.pay!(TestRecords::DECLINED_CARD, at: moment("2026-08-20 10:00:00"))

    get admin_order_path(order)

    assert_select "[data-payment=?] [data-cell=?]", order.payments.sole.id, "decline_reason",
      text: "Generic decline"
  end

  test "the page says so where nothing has been sent back" do
    sign_in_as_admin
    order = order_for(create_verified_customer, create_listing)

    get admin_order_path(order)

    assert_select "[data-empty=?]", "order_refunds"
    assert_select "[data-empty=?]", "order_payments"
  end

  test "an order path carrying another table's id is not found" do
    sign_in_as_admin

    get "/admin/orders/#{unused_id(:ful)}"

    assert_response :not_found
  end

  test "an order id nothing was written for is not found" do
    sign_in_as_admin

    get admin_order_path(id: unused_id(:ord))

    assert_response :not_found
  end
end
