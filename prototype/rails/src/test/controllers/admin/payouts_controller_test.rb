require "test_helper"

class Admin::PayoutsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor runs no payout" do
    post admin_payouts_path

    assert_redirected_to admin_login_path
    assert_empty Payout.all
  end

  test "running the payout settles the released escrow of the last completed week, for every seller" do
    sign_in_as_admin
    seller = create_seller
    other = other_seller
    create_delivered_fulfillment(seller)
    create_delivered_fulfillment(other)
    backdate_ledger_to_last_completed_week

    post admin_payouts_path

    assert_redirected_to admin_payouts_path
    assert_equal 40_500, seller.payouts.sole.amount_cents
    assert_equal 40_500, other.payouts.sole.amount_cents
  end

  test "it reports how many payouts it wrote and what they came to" do
    seller = create_seller
    create_delivered_fulfillment(seller)
    backdate_ledger_to_last_completed_week
    sign_in_as_admin

    post admin_payouts_path
    follow_redirect!

    assert_select "[data-flash=notice]", text: "Weekly payout run: 1 payout(s) totalling $405.00."
  end

  test "as_of names the week to settle" do
    shop = create_seller(shop_name: "Blue Kiln Studio")
    deliver_a_sale(shop)
    sign_in_as_admin

    post admin_payouts_path, params: { as_of: "2026-08-24" }

    assert_equal Date.new(2026, 8, 17), shop.payouts.sole.period_start
  end

  test "a second run of the same period pays nothing again" do
    seller = create_seller
    create_delivered_fulfillment(seller)
    backdate_ledger_to_last_completed_week
    sign_in_as_admin
    post admin_payouts_path

    post admin_payouts_path
    follow_redirect!

    assert_equal 1, seller.payouts.count
    assert_select "[data-flash=notice]", text: "Weekly payout run: 0 payout(s) totalling $0.00."
  end

  test "the list narrows to one seller's payouts" do
    sign_in_as_admin
    mine = create_seller
    theirs = other_seller
    create_delivered_fulfillment(mine)
    create_delivered_fulfillment(theirs)
    backdate_ledger_to_last_completed_week
    Payout.run_weekly

    get admin_payouts_path(seller: mine.id)

    assert_select "[data-payout=?]", mine.payouts.sole.id
    assert_select "[data-payout=?]", theirs.payouts.sole.id, false
  end

  test "the list says so where nothing matches" do
    sign_in_as_admin

    get admin_payouts_path

    assert_select "[data-empty=?]", "payouts"
  end

  test "a seller filter carrying another table's id is a bad request" do
    sign_in_as_admin

    get admin_payouts_path(seller: unused_id(:cus))

    assert_response :bad_request
  end

  test "the seller portal offers no route that runs a payout" do
    post "/seller/earnings/payout"

    assert_response :not_found
  end

  private

  def backdate_ledger_to_last_completed_week
    LedgerEntry.update_all(occurred_at: PayoutPeriod.ending_before(Time.current).ends_at - 1.day)
  end

  def deliver_a_sale(shop)
    order = paid_order_for(create_verified_customer, create_listing(shop, price_cents: 45_000))
    fulfillment = order.fulfillments.sole
    fulfillment.ship!(carrier: "USPS", tracking_number: "9400111899", at: moment("2026-08-20 11:00:00"))
    fulfillment.deliver!(at: moment("2026-08-21 11:00:00"))
  end
end
