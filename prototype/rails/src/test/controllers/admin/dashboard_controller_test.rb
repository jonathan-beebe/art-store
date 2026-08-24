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
    assert_select "nav a[href=?]", admin_accounting_path
    assert_select "nav a[href=?]", admin_ledger_path
    assert_select "nav a[href=?]", admin_stats_path
  end

  test "it counts sellers and customers by standing" do
    sign_in_as_admin
    create_seller
    create_verified_customer
    create_anonymous_customer

    get admin_root_path

    assert_select "[data-stat=sellers] dd", text: "1"
    assert_select "[data-stat=verified-customers] dd", text: "1"
    assert_select "[data-stat=anonymous-customers] dd", text: "1"
  end

  test "every listing, order and fulfillment status is listed, even at zero" do
    sign_in_as_admin

    get admin_root_path

    assert_response :success
    Listing.statuses.keys.each { |status| assert_select "[data-stat=?] dd", "listing-#{status}", text: "0" }
    Order.statuses.keys.each { |status| assert_select "[data-stat=?] dd", "order-#{status}", text: "0" }
    Fulfillment.statuses.keys.each { |status| assert_select "[data-stat=?] dd", "fulfillment-#{status}", text: "0" }
  end

  test "a status with a row is counted under its own tally, and the rest stay zero" do
    sign_in_as_admin
    create_listing(status: :draft)

    get admin_root_path

    assert_select "[data-stat=listing-draft] dd", text: "1"
    assert_select "[data-stat=listing-for_sale] dd", text: "0"
  end

  test "the money section reads the platform's folded balance" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = paid_order_for(create_verified_customer, create_listing(seller, price_cents: 45_000))
      .fulfillments.sole
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))

    get admin_root_path

    assert_select "[data-stat=held] dd", text: "$0.00"
    assert_select "[data-stat=available] dd", text: "$405.00"
    assert_select "[data-stat=fees-earned] dd", text: "$45.00"
    assert_select "[data-stat=fees-refunded] dd", text: "$0.00"
    assert_select "[data-stat=refunded] dd", text: "$0.00"
  end

  test "page views this week counts the seven days ending today" do
    sign_in_as_admin
    PageViewCount.record!(path_pattern: "/art/:slug", at: Time.current)
    PageViewCount.record!(path_pattern: "/art/:slug", at: 30.days.ago)

    get admin_root_path

    assert_select "[data-stat=views-this-week] dd", text: "1"
  end

  test "the folded money and tallies cost the same statements however many sellers hold money" do
    sign_in_as_admin
    build_delivered_seller
    one = count_queries { get admin_root_path }

    4.times { build_delivered_seller }
    five = count_queries { get admin_root_path }

    assert_equal one, five
  end

  private

  def build_delivered_seller
    seller = create_seller
    fulfillment = paid_order_for(create_verified_customer, create_listing(seller, price_cents: 45_000))
      .fulfillments.sole
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))
  end
end
