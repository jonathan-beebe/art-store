require "commerce_test_case"

module Escrow
  class RunWeeklyPayoutTest < CommerceTestCase
    def test_it_pays_a_seller_whose_delivery_landed_inside_the_period
      shop = seller
      deliver_a_sale(shop)

      payouts = run_payout("2026-08-24 09:00:00")

      assert_equal 1, payouts.size
      assert_equal shop.id, payouts.sole.seller_id
      assert_equal 40_500, payouts.sole.amount_cents
      assert_equal Date.new(2026, 8, 17), payouts.sole.period_start
      assert_equal Date.new(2026, 8, 23), payouts.sole.period_end
    end

    def test_a_payout_writes_a_matching_paid_out_entry_at_the_close_of_the_period
      deliver_a_sale(seller)

      payouts = run_payout("2026-08-24 09:00:00")

      entry = LedgerEntry.find_by(entry_type: Domain::Escrow::LedgerEntryType::PAID_OUT)
      assert_equal(-40_500, entry.amount_cents)
      assert_equal payouts.sole.id, entry.payout_id
      assert_equal Time.utc(2026, 8, 23, 23, 59, 59), entry.occurred_at
    end

    def test_running_the_same_period_twice_pays_once
      deliver_a_sale(seller)
      run_payout("2026-08-24 09:00:00")

      second = run_payout("2026-08-25 09:00:00")

      assert_empty second
      assert_equal 1, Payout.count
    end

    def test_it_pays_each_seller_their_own_released_amount
      first = seller("Blue Kiln Studio")
      second = seller("Rye Press")
      deliver_a_sale(first)
      deliver_a_sale(second, price_cents: 10_000)

      run_payout("2026-08-24 09:00:00")

      assert_equal({ first.id => 40_500, second.id => 9000 }, Payout.order(:seller_id).pluck(:seller_id, :amount_cents).to_h)
    end

    def test_money_still_held_in_escrow_is_not_paid_out
      paid_order_for(customer, listing(seller))

      assert_empty run_payout("2026-08-24 09:00:00")
      assert_equal 0, Payout.count
    end

    def test_a_delivery_after_the_period_ends_waits_for_the_next_run
      deliver_a_sale(seller, delivered_at: "2026-08-24 11:00:00")

      assert_empty run_payout("2026-08-24 12:00:00")
    end

    private

    def run_payout(as_of)
      RunWeeklyPayout.new.call(as_of: moment(as_of))
    end

    def deliver_a_sale(shop, price_cents: 45_000, delivered_at: "2026-08-21 11:00:00")
      order = paid_order_for(customer, listing(shop, price_cents: price_cents))
      fulfillment = Fulfillments::MarkShipped.new.call(
        fulfillment: order.fulfillments.sole, carrier: "USPS", tracking_number: "9400111899",
        now: moment("2026-08-20 11:00:00")
      )
      Fulfillments::ConfirmDelivered.new.call(fulfillment: fulfillment, now: moment(delivered_at))
    end
  end
end
