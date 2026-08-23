require "test_helper"

class PayoutTest < ActiveSupport::TestCase
  test "a payout reads its amount in money" do
    payout = Payout.create!(
      seller: create_seller, period_start: Date.new(2026, 8, 17), period_end: Date.new(2026, 8, 23),
      amount_cents: 40_500, paid_at: moment("2026-08-24 09:00:00")
    )

    assert_equal "$405.00", payout.amount.format
  end

  test "the weekly run pays a seller whose delivery landed inside the period" do
    shop = create_seller
    deliver_a_sale(shop)

    payouts = run_weekly("2026-08-24 09:00:00")

    assert_equal 1, payouts.size
    assert_equal shop.id, payouts.sole.seller_id
    assert_equal 40_500, payouts.sole.amount_cents
    assert_equal Date.new(2026, 8, 17), payouts.sole.period_start
    assert_equal Date.new(2026, 8, 23), payouts.sole.period_end
  end

  test "a payout writes a matching paid out entry at the close of the period" do
    deliver_a_sale(create_seller)

    payouts = run_weekly("2026-08-24 09:00:00")

    entry = LedgerEntry.paid_out.sole
    assert_equal(-40_500, entry.amount_cents)
    assert_equal payouts.sole.id, entry.payout_id
    assert_equal Time.utc(2026, 8, 23, 23, 59, 59), entry.occurred_at
  end

  test "running the same period twice pays once" do
    deliver_a_sale(create_seller)
    run_weekly("2026-08-24 09:00:00")

    second = run_weekly("2026-08-25 09:00:00")

    assert_empty second
    assert_equal 1, Payout.count
  end

  test "it pays each seller their own released amount" do
    first = create_seller(shop_name: "Blue Kiln Studio")
    second = create_seller(shop_name: "Rye Press")
    deliver_a_sale(first)
    deliver_a_sale(second, price_cents: 10_000)

    run_weekly("2026-08-24 09:00:00")

    assert_equal({ first.id => 40_500, second.id => 9000 }, Payout.order(:seller_id).pluck(:seller_id, :amount_cents).to_h)
  end

  test "money still held in escrow is not paid out" do
    paid_order_for(create_verified_customer, create_listing)

    assert_empty run_weekly("2026-08-24 09:00:00")
    assert_equal 0, Payout.count
  end

  test "a delivery after the period ends waits for the next run" do
    deliver_a_sale(create_seller, delivered_at: "2026-08-24 11:00:00")

    assert_empty run_weekly("2026-08-24 12:00:00")
  end

  test "a run with no moment behind it settles the week before now" do
    freeze_time do
      assert_empty Payout.run_weekly
    end
  end

  private

  def run_weekly(as_of)
    Payout.run_weekly(as_of: moment(as_of))
  end

  def deliver_a_sale(shop, price_cents: 45_000, delivered_at: "2026-08-21 11:00:00")
    order = paid_order_for(create_verified_customer, create_listing(shop, price_cents: price_cents))
    fulfillment = order.fulfillments.sole
    fulfillment.ship!(carrier: "USPS", tracking_number: "9400111899", at: moment("2026-08-20 11:00:00"))
    fulfillment.deliver!(at: moment(delivered_at))
  end
end
