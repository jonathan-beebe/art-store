require "test_helper"

class Admin::CustomersControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    customer = create_verified_customer

    get admin_customer_path(customer)

    assert_redirected_to admin_login_path
  end

  test "the page names the customer and the address behind them" do
    sign_in_as_admin
    customer = create_verified_customer(name: "Casey Whitfield", email: "casey@example.com")

    get admin_customer_path(customer)

    assert_response :success
    assert_select "h1", text: "Casey Whitfield"
    assert_select "[data-field=?]", "email", text: "casey@example.com"
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

  test "a visitor who has given no address has no page" do
    sign_in_as_admin

    get admin_customer_path(create_anonymous_customer)

    assert_response :not_found
  end

  test "a customer id nothing was written for is not found" do
    sign_in_as_admin

    get admin_customer_path(id: 0)

    assert_response :not_found
  end
end
