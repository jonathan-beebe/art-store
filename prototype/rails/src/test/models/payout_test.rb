require "test_helper"

class PayoutTest < ActiveSupport::TestCase
  test "a payout reads its amount in money" do
    payout = Payout.create!(
      seller: create_seller, period_start: Date.new(2026, 8, 17), period_end: Date.new(2026, 8, 23),
      amount_cents: 40_500, paid_at: moment("2026-08-24 09:00:00")
    )

    assert_equal "$405.00", payout.amount.format
  end
end
