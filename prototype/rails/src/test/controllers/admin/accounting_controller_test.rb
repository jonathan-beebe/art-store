require "test_helper"

class Admin::AccountingControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_accounting_path

    assert_redirected_to admin_login_path
  end

  test "it says so where nobody has opened a shop" do
    sign_in_as_admin

    get admin_accounting_path

    assert_select "[data-empty=?]", "accounts"
  end

  test "it reconciles a seller who has moved no money" do
    sign_in_as_admin
    seller = create_seller

    get admin_accounting_path

    assert_select "[data-account=?] [data-cell=?]", seller.id, "held", text: "$0.00"
    assert_select "[data-account=?] [data-cell=?]", seller.id, "available", text: "$0.00"
  end

  test "a delivered sale reads its released balance and its earned fee" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = paid_order_for(create_verified_customer, create_listing(seller, price_cents: 45_000))
      .fulfillments.sole
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))

    get admin_accounting_path

    assert_select "[data-account=?] [data-cell=?]", seller.id, "available", text: "$405.00"
    assert_select "[data-account=?] [data-cell=?]", seller.id, "fees_earned", text: "$45.00"
    assert_select "[data-account=?] [data-cell=?]", seller.id, "refunded", text: "$0.00"
  end

  test "a declined sale forgoes its fee and shows what went back" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = paid_order_for(create_verified_customer, create_listing(seller, price_cents: 45_000))
      .fulfillments.sole
    fulfillment.decline!(reason: "Out of stock", by: seller, at: moment("2026-08-21 09:00:00"))

    get admin_accounting_path

    assert_select "[data-account=?] [data-cell=?]", seller.id, "fees_refunded", text: "$45.00"
    assert_select "[data-account=?] [data-cell=?]", seller.id, "refunded", text: "$450.00"
  end

  test "the platform money section reads the folded totals" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = paid_order_for(create_verified_customer, create_listing(seller, price_cents: 45_000))
      .fulfillments.sole
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))

    get admin_accounting_path

    assert_select "[data-stat=available] dd", text: "$405.00"
    assert_select "[data-stat=fees-earned] dd", text: "$45.00"
  end

  test "the fold costs the same statements however many sellers hold money" do
    sign_in_as_admin
    create_seller
    one = count_queries { get admin_accounting_path }

    4.times { create_seller }
    five = count_queries { get admin_accounting_path }

    assert_equal one, five
  end
end
