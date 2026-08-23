require "test_helper"

class Seller::DashboardControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get seller_root_path

    assert_redirected_to seller_login_path
  end

  test "the dashboard renders in the seller layout" do
    signed_in_seller

    get seller_root_path

    assert_response :success
    assert_select "body[data-site=?]", "seller"
    assert_select "head link[rel=stylesheet][href*=?]", "tailwind"
    assert_select "nav a[href=?]", seller_listings_path
  end

  test "it tallies the seller's listings by status" do
    seller = signed_in_seller
    create_listing(seller, status: "draft")
    create_listing(seller, status: "for_sale")
    create_listing(seller, status: "for_sale")

    get seller_root_path

    assert_select "[data-stat=?]", "draft", text: "1"
    assert_select "[data-stat=?]", "for_sale", text: "2"
    assert_select "[data-stat=?]", "sold", text: "0"
  end

  test "another seller's listings are counted on their own dashboard" do
    signed_in_seller
    create_listing(other_seller, status: "for_sale")

    get seller_root_path

    assert_select "[data-stat=?]", "for_sale", text: "0"
  end

  test "it counts the fulfillments waiting to be shipped" do
    seller = signed_in_seller
    create_fulfillment(seller)

    get seller_root_path

    assert_select "[data-stat=?]", "awaiting_shipment", text: "1"
  end

  test "it shows the escrow balances" do
    seller = signed_in_seller
    create_fulfillment(seller)

    get seller_root_path

    assert_select "[data-stat=?]", "held", text: "$405.00"
    assert_select "[data-stat=?]", "available", text: "$0.00"
  end

  test "it counts unread notifications and lists the five most recent" do
    seller = signed_in_seller
    6.times { |index| create_notification(seller, subject: "Notice #{index}") }
    create_notification(seller, subject: "Read one", read_at: Time.current)

    get seller_root_path

    assert_select "[data-stat=?]", "unread_notifications", text: "6"
    assert_select "[data-recent-notification]", 5
    assert_select "[data-recent-notification]", text: /Read one/
  end

  test "another seller's notifications stay off this dashboard" do
    signed_in_seller
    create_notification(other_seller, subject: "Rival notice")

    get seller_root_path

    assert_select "[data-stat=?]", "unread_notifications", text: "0"
    assert_select "[data-recent-notification]", false
  end
end
