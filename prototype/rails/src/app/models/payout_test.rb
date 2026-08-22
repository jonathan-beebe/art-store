require "commerce_test_case"

class PayoutTest < CommerceTestCase
  def test_a_payout_reads_its_amount_in_money
    payout = Payout.create!(
      seller: seller, period_start: Date.new(2026, 8, 17), period_end: Date.new(2026, 8, 23),
      amount_cents: 40_500, paid_at: moment("2026-08-24 09:00:00")
    )

    assert_equal "$405.00", payout.amount.format
  end
end
