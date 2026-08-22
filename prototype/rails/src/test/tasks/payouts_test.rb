require "commerce_test_case"
require "rake"

class PayoutsTaskTest < CommerceTestCase
  setup do
    Rails.application.load_tasks unless Rake::Task.task_defined?("payouts:run")
    Rake::Task["payouts:run"].reenable
  end

  test "it pays the week that ended before the date it is given" do
    shop = seller("Blue Kiln Studio")
    deliver_a_sale(shop)

    output = run_task("2026-08-24 09:00:00")

    assert_includes output, "Payout period 2026-08-17 to 2026-08-23"
    assert_includes output, "Blue Kiln Studio $405.00"
    assert_includes output, "1 seller(s) paid."
    assert_equal 40_500, Payout.sole.amount_cents
  end

  test "it says so when no seller has a released balance" do
    output = run_task("2026-08-24 09:00:00")

    assert_includes output, "No seller has a released balance for this period."
    assert_equal 0, Payout.count
  end

  private

  def run_task(as_of)
    capture_io { Rake::Task["payouts:run"].invoke(as_of) }.first
  end

  def deliver_a_sale(shop)
    order = paid_order_for(customer, listing(shop, price_cents: 45_000))
    fulfillment = Fulfillments::MarkShipped.new.call(
      fulfillment: order.fulfillments.sole, carrier: "USPS", tracking_number: "9400111899",
      now: moment("2026-08-20 11:00:00")
    )
    Fulfillments::ConfirmDelivered.new.call(fulfillment: fulfillment, now: moment("2026-08-21 11:00:00"))
  end
end
