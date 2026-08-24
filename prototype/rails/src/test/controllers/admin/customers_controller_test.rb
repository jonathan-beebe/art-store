require "test_helper"

class Admin::CustomersControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_customers_path

    assert_redirected_to admin_login_path
  end

  test "the list names every customer, the browsers holding a cart included" do
    sign_in_as_admin
    verified = create_verified_customer(name: "Casey Whitfield")
    anonymous = create_anonymous_customer

    get admin_customers_path

    assert_response :success
    assert_select "[data-customer=?] a[href=?]", verified.id, admin_customer_path(verified), text: "Casey Whitfield"
    assert_select "[data-customer=?] [data-cell=?]", anonymous.id, "standing", text: "Anonymous"
  end

  test "the list counts the orders, favorites, and cart lines behind each customer" do
    sign_in_as_admin
    customer = create_verified_customer
    listing = create_listing
    order_for(customer, listing)
    customer.toggle_favorite(listing)
    cart_holding(customer, create_listing)

    get admin_customers_path

    assert_select "[data-customer=?] [data-cell=?]", customer.id, "orders", text: "1"
    assert_select "[data-customer=?] [data-cell=?]", customer.id, "favorites", text: "1"
    assert_select "[data-customer=?] [data-cell=?]", customer.id, "cart_lines", text: "1"
  end

  test "no standing filter lists everyone" do
    sign_in_as_admin
    verified = create_verified_customer
    anonymous = create_anonymous_customer

    get admin_customers_path

    assert_select "[data-customer=?]", verified.id
    assert_select "[data-customer=?]", anonymous.id
  end

  test "an empty standing filter lists everyone" do
    sign_in_as_admin
    verified = create_verified_customer
    anonymous = create_anonymous_customer

    get admin_customers_path(standing: "")

    assert_select "[data-customer=?]", verified.id
    assert_select "[data-customer=?]", anonymous.id
  end

  test "standing=all lists everyone" do
    sign_in_as_admin
    verified = create_verified_customer
    anonymous = create_anonymous_customer

    get admin_customers_path(standing: "all")

    assert_select "[data-customer=?]", verified.id
    assert_select "[data-customer=?]", anonymous.id
  end

  test "standing=verified drops the browsers holding a cart" do
    sign_in_as_admin
    verified = create_verified_customer
    anonymous = create_anonymous_customer

    get admin_customers_path(standing: "verified")

    assert_select "[data-customer=?]", verified.id
    assert_select "[data-customer=?]", anonymous.id, false
  end

  test "standing=anonymous keeps only the browsers holding a cart" do
    sign_in_as_admin
    verified = create_verified_customer
    anonymous = create_anonymous_customer

    get admin_customers_path(standing: "anonymous")

    assert_select "[data-customer=?]", anonymous.id
    assert_select "[data-customer=?]", verified.id, false
  end

  test "standing=blocked lists nobody while nothing blocks a customer" do
    sign_in_as_admin
    create_verified_customer

    get admin_customers_path(standing: "blocked")

    assert_response :success
    assert_select "[data-empty=?]", "customers"
  end

  test "standing=blocked keeps only the customers a block stands over" do
    sign_in_as_admin
    blocked = create_verified_customer
    blocked.block!(reason: "Chargeback fraud.", by: create_admin)
    untouched = create_verified_customer

    get admin_customers_path(standing: "blocked")

    assert_select "[data-customer=?]", blocked.id
    assert_select "[data-customer=?]", untouched.id, false
  end

  test "a standing the page does not offer is a bad request" do
    sign_in_as_admin

    get admin_customers_path(standing: "wat")

    assert_response :bad_request
  end

  test "the list costs the same however many customers it holds" do
    sign_in_as_admin
    create_verified_customer
    one = count_queries { get admin_customers_path }

    4.times { create_verified_customer }
    five = count_queries { get admin_customers_path }

    assert_equal one, five
  end

  test "the page names the customer and the address behind them" do
    sign_in_as_admin
    customer = create_verified_customer(name: "Casey Whitfield", email: "casey@example.com")

    get admin_customer_path(customer)

    assert_response :success
    assert_select "h1", text: "Casey Whitfield"
    assert_select "[data-field=?]", "email", text: "casey@example.com"
    assert_select "[data-field=?]", "standing", text: "Verified"
  end

  test "an unnamed customer is named by the local part of their address" do
    sign_in_as_admin
    customer = create_verified_customer(name: nil, email: "casey@example.com")

    get admin_customer_path(customer)

    assert_select "h1", text: "casey"
  end

  test "the page counts the orders the customer has placed" do
    sign_in_as_admin
    customer = create_verified_customer
    order_for(customer, create_listing)

    get admin_customer_path(customer)

    assert_select "[data-field=?]", "orders", text: "1"
  end

  test "the page lists the orders, favorites, and cart the customer holds" do
    sign_in_as_admin
    customer = create_verified_customer
    ordered = create_listing
    favorited = create_listing
    carted = create_listing
    order = order_for(customer, ordered)
    customer.toggle_favorite(favorited)
    cart_holding(customer, carted)

    get admin_customer_path(customer)

    assert_select "[data-order=?]", order.id
    assert_select "[data-favorite] a", text: favorited.title
    assert_select "[data-cart-item] a", text: carted.title
  end

  test "the page reads a visitor who has given no address" do
    sign_in_as_admin
    anonymous = create_anonymous_customer

    get admin_customer_path(anonymous)

    assert_response :success
    assert_select "[data-field=?]", "standing", text: "Anonymous"
  end

  test "a visitor with no address has nobody to message" do
    sign_in_as_admin

    get admin_customer_path(create_anonymous_customer)

    assert_select "input[type=submit][value=?]", "Message", false
  end

  test "the page names both sides of every merge the customer was in" do
    sign_in_as_admin
    anonymous = create_anonymous_customer
    verified = create_verified_customer(name: "Casey Whitfield")
    verified.absorb(anonymous)

    get admin_customer_path(verified)

    assert_select "[data-merge=?]", anonymous.id, text: /Absorbed a visitor/

    get admin_customer_path(anonymous)

    assert_select "[data-merge=?]", verified.id, text: /Folded into an account/
  end

  test "the page says so where the customer has done nothing" do
    sign_in_as_admin

    get admin_customer_path(create_verified_customer)

    assert_select "[data-empty=?]", "customer_orders"
    assert_select "[data-empty=?]", "customer_favorites"
    assert_select "[data-empty=?]", "customer_cart"
    assert_select "[data-empty=?]", "customer_blocks"
    assert_select "[data-empty=?]", "customer_merges"
  end

  test "the page reads the block history and the reason it stands over the customer" do
    sign_in_as_admin
    customer = create_verified_customer
    customer.block!(reason: "Chargeback fraud.", by: create_admin)

    get admin_customer_path(customer)

    assert_select "[data-field=?]", "blocked", text: "Blocked"
    assert_select "[data-block] th", text: "Chargeback fraud."
    assert_select "form[action=?] button", lift_admin_customer_blocks_path(customer), text: "Lift block"
  end

  test "a customer path carrying another table's id is not found" do
    sign_in_as_admin

    get "/admin/customers/#{unused_id(:sel)}"

    assert_response :not_found
  end

  test "a customer id nothing was written for is not found" do
    sign_in_as_admin

    get admin_customer_path(id: unused_id(:cus))

    assert_response :not_found
  end
end
