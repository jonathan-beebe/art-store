require "test_helper"

class Admin::SellersControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    seller = create_seller

    get admin_seller_path(seller)

    assert_redirected_to admin_login_path
  end

  test "the page names the shop and the address behind it" do
    sign_in_as_admin
    seller = create_seller(shop_name: "Terra & Glaze", email: "maya@example.com")

    get admin_seller_path(seller)

    assert_response :success
    assert_select "h1", text: "Terra & Glaze"
    assert_select "[data-field=?]", "email", text: "maya@example.com"
  end

  test "the page counts the listings the seller owns" do
    sign_in_as_admin
    seller = create_seller
    create_listing(seller)
    create_listing(seller)
    create_listing(other_seller)

    get admin_seller_path(seller)

    assert_select "[data-field=?]", "listings", text: "2"
  end

  test "a seller path carrying another table's id is not found" do
    sign_in_as_admin

    get "/admin/sellers/#{unused_id(:cus)}"

    assert_response :not_found
  end

  test "a seller id nothing was written for is not found" do
    sign_in_as_admin

    get admin_seller_path(id: unused_id(:sel))

    assert_response :not_found
  end
end
