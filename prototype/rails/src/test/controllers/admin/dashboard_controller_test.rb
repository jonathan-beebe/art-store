require "test_helper"

class Admin::DashboardControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_root_path

    assert_redirected_to admin_login_path
    assert_equal "Sign in to reach the admin site.", flash[:alert]
  end

  test "the dashboard renders in the admin layout" do
    sign_in_as_admin

    get admin_root_path

    assert_response :success
    assert_select "body[data-site=?]", "admin"
    assert_select "head link[rel=stylesheet][href*=?]", "tailwind"
    assert_select "nav a[href=?]", admin_root_path
  end

  test "it lists every seller with a link to their page" do
    sign_in_as_admin
    seller = create_seller(shop_name: "Terra & Glaze")

    get admin_root_path

    assert_select "[data-seller=?] a[href=?]", seller.id.to_s, admin_seller_path(seller), text: "Terra & Glaze"
    assert_select "[data-seller=?]", seller.id.to_s, text: /#{seller.email}/
  end

  test "it lists the verified customers with a link to their page" do
    sign_in_as_admin
    customer = create_verified_customer(name: "Casey Whitfield")

    get admin_root_path

    assert_select "[data-customer=?] a[href=?]", customer.id.to_s, admin_customer_path(customer),
      text: "Casey Whitfield"
  end

  test "a visitor who has given no address stays off the customer list" do
    sign_in_as_admin
    anonymous = create_anonymous_customer

    get admin_root_path

    assert_select "[data-customer=?]", anonymous.id.to_s, false
  end

  test "it says so where nobody has signed up" do
    sign_in_as_admin

    get admin_root_path

    assert_select "[data-empty=?]", "sellers"
    assert_select "[data-empty=?]", "customers"
  end
end
