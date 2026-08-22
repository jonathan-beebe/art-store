require "seller_portal_test_case"

class Seller::EarningsControllerTest < SellerPortalTestCase
  test "a signed-out visitor sees no earnings" do
    get seller_earnings_path

    assert_redirected_to seller_login_path
  end

  test "a sale waiting on delivery is held in escrow" do
    seller = signed_in_seller
    create_fulfillment(seller)

    get seller_earnings_path

    assert_response :success
    assert_select "[data-stat=held]", text: "$405.00"
    assert_select "[data-stat=available]", text: "$0.00"
    assert_select "[data-stat=paid_out]", text: "$0.00"
  end

  test "a delivered sale is available to pay out" do
    seller = signed_in_seller
    create_delivered_fulfillment(seller)

    get seller_earnings_path

    assert_select "[data-stat=held]", text: "$0.00"
    assert_select "[data-stat=available]", text: "$405.00"
  end

  test "each sale carries its subtotal, fee, net, and status" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller, listing: create_listing(seller, title: "Harbour at Dusk"))

    get seller_earnings_path

    assert_select "[data-fulfillment=?]", fulfillment.id.to_s do
      assert_select "td", text: "Harbour at Dusk"
      assert_select "[data-cell=subtotal]", text: "$450.00"
      assert_select "[data-cell=fee]", text: "$45.00"
      assert_select "[data-cell=net]", text: "$405.00"
      assert_select "[data-cell=status]", text: "Awaiting shipment"
    end
  end

  test "another seller's sales and balances stay off the page" do
    signed_in_seller
    rival = create_fulfillment(other_seller)

    get seller_earnings_path

    assert_select "[data-fulfillment=?]", rival.id.to_s, false
    assert_select "[data-stat=held]", text: "$0.00"
    assert_select "p", text: "No sales yet."
  end

  test "a settled week shows up in the payouts table" do
    seller = signed_in_seller
    create_delivered_fulfillment(seller)
    settle_last_completed_week

    get seller_earnings_path

    assert_select "[data-payout] [data-cell=amount]", text: "$405.00"
    assert_select "[data-stat=paid_out]", text: "$405.00"
    assert_select "[data-stat=available]", text: "$0.00"
  end

  test "the page offers the debug payout control" do
    signed_in_seller

    get seller_earnings_path

    assert_select "form[action=?][method=post]", seller_earnings_payout_path
    assert_select "form[action=?] button[type=submit]", seller_earnings_payout_path, text: "Run weekly payout now"
  end

  private

  def settle_last_completed_week
    period = Domain::Escrow::PayoutPeriod.ending_before(Time.current)
    LedgerEntry.update_all(occurred_at: period.ends_at - 1.day)
    Escrow::RunWeeklyPayout.new.call(as_of: Time.current)
  end
end
