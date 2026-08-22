require "seller_portal_test_case"

class Seller::PayoutsControllerTest < SellerPortalTestCase
  test "a signed-out visitor runs no payout" do
    post seller_earnings_payout_path

    assert_redirected_to seller_login_path
    assert_empty Payout.all
  end

  test "running the payout settles the released escrow of the last completed week" do
    seller = signed_in_seller
    create_delivered_fulfillment(seller)
    backdate_ledger_to_last_completed_week

    post seller_earnings_payout_path

    assert_redirected_to seller_earnings_path
    payout = seller.payouts.sole
    assert_equal 40_500, payout.amount_cents
    assert_equal Domain::Escrow::PayoutPeriod.ending_before(Time.current).first_day, payout.period_start
  end

  test "it reports how many payouts it wrote and what they came to" do
    seller = signed_in_seller
    create_delivered_fulfillment(seller)
    backdate_ledger_to_last_completed_week

    post seller_earnings_payout_path
    follow_redirect!

    assert_select "[data-flash=notice]", text: "Weekly payout run: 1 payout(s) totalling $405.00."
  end

  test "a run with nothing released pays nobody" do
    signed_in_seller
    create_fulfillment(other_seller)

    post seller_earnings_payout_path
    follow_redirect!

    assert_empty Payout.all
    assert_select "[data-flash=notice]", text: "Weekly payout run: 0 payout(s) totalling $0.00."
  end

  test "a second run of the same period pays nothing again" do
    seller = signed_in_seller
    create_delivered_fulfillment(seller)
    backdate_ledger_to_last_completed_week
    post seller_earnings_payout_path

    post seller_earnings_payout_path
    follow_redirect!

    assert_equal 1, seller.payouts.count
    assert_select "[data-flash=notice]", text: "Weekly payout run: 0 payout(s) totalling $0.00."
  end

  private

  def backdate_ledger_to_last_completed_week
    LedgerEntry.update_all(occurred_at: Domain::Escrow::PayoutPeriod.ending_before(Time.current).ends_at - 1.day)
  end
end
