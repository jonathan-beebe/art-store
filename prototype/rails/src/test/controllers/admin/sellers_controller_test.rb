require "test_helper"

class Admin::SellersControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_sellers_path

    assert_redirected_to admin_login_path
  end

  test "the list names every seller with a link to their page" do
    sign_in_as_admin
    seller = create_seller(shop_name: "Terra & Glaze")

    get admin_sellers_path

    assert_response :success
    assert_select "[data-seller=?] a[href=?]", seller.id, admin_seller_path(seller), text: "Terra & Glaze"
  end

  test "the list counts the listings and the sales behind each seller" do
    sign_in_as_admin
    seller = create_seller
    create_listing(seller)
    create_delivered_fulfillment(seller)

    get admin_sellers_path

    assert_select "[data-seller=?] [data-cell=?]", seller.id, "listings", text: "2"
    assert_select "[data-seller=?] [data-cell=?]", seller.id, "fulfillments", text: "1"
  end

  test "the list folds each seller's ledger into a balance" do
    sign_in_as_admin
    seller = create_seller
    create_delivered_fulfillment(seller)

    get admin_sellers_path

    assert_select "[data-seller=?] [data-cell=?]", seller.id, "held", text: "$0.00"
    assert_select "[data-seller=?] [data-cell=?]", seller.id, "available", text: "$405.00"
  end

  test "a seller with no ledger reads a zero balance" do
    sign_in_as_admin
    seller = create_seller

    get admin_sellers_path

    assert_select "[data-seller=?] [data-cell=?]", seller.id, "available", text: "$0.00"
  end

  test "the list says so where nobody has opened a shop" do
    sign_in_as_admin

    get admin_sellers_path

    assert_select "[data-empty=?]", "sellers"
  end

  test "the list costs the same however many sellers it holds" do
    sign_in_as_admin
    create_seller
    one = count_queries { get admin_sellers_path }

    4.times { create_seller }
    five = count_queries { get admin_sellers_path }

    assert_equal one, five
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

  test "the page lists the seller's listings, sales, and payouts beside a folded balance" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = create_delivered_fulfillment(seller)
    payout = Payout.run_weekly(as_of: moment("2026-08-31 09:00:00")).sole

    get admin_seller_path(seller)

    assert_select "[data-listing=?]", fulfillment.items.sole.listing_id
    assert_select "[data-fulfillment=?]", fulfillment.id
    assert_select "[data-payout=?]", payout.id
    assert_select "[data-field=?]", "paid_out", text: "$405.00"
  end

  test "the page says so where the seller has done nothing" do
    sign_in_as_admin

    get admin_seller_path(create_seller)

    assert_select "[data-empty=?]", "seller_listings"
    assert_select "[data-empty=?]", "seller_fulfillments"
    assert_select "[data-empty=?]", "seller_payouts"
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
