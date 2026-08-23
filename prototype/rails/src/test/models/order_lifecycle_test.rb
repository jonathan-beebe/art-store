require "test_helper"

# Walks the whole order lifecycle across two sellers. The other order tests
# cover one step each; these two protect the way the steps add up.
class OrderLifecycleTest < ActiveSupport::TestCase
  test "an order runs from the cart to the weekly payout" do
    painter = create_seller(shop_name: "Blue Kiln Studio")
    printer = create_seller(shop_name: "Rye Press")
    painting = create_listing(painter, price_cents: 45_000, quantity: 1)
    print = create_listing(printer, price_cents: 12_000, quantity: 1)

    order = order_for(create_verified_customer, painting, print)

    assert_predicate order, :awaiting_payment?
    assert_equal 57_000, order.total_cents
    assert_equal "sold", painting.reload.status

    order.pay!(APPROVED_CARD, at: moment("2026-08-20 10:00:00"))

    assert_predicate order, :paid?
    assert_equal 2, Notification.where(subject: "Item sold").count
    assert_equal [40_500, 10_800], held_per_seller(painter, printer)

    painting_shipment = order.fulfillments.find_by(seller: painter)
    print_shipment = order.fulfillments.find_by(seller: printer)

    ship(painting_shipment, "USPS", "9400111899", "2026-08-21 11:00:00")
    assert_predicate order.reload, :partially_shipped?

    ship(print_shipment, "FedEx", "7712349", "2026-08-21 12:00:00")
    assert_predicate order.reload, :shipped?
    assert_equal 2, Notification.where(subject: "Order shipped").count

    deliver(painting_shipment, "2026-08-22 09:00:00")
    assert_predicate order.reload, :shipped?

    deliver(print_shipment, "2026-08-22 10:00:00")
    assert_predicate order.reload, :delivered?
    assert_equal [40_500, 10_800], available_per_seller(painter, printer)

    payouts = Escrow::RunWeeklyPayout.new.call(as_of: moment("2026-08-24 09:00:00"))

    assert_equal 2, payouts.size
    assert_equal({ painter.id => 40_500, printer.id => 10_800 }, Payout.order(:seller_id).pluck(:seller_id, :amount_cents).to_h)
    assert_equal [0, 0], available_per_seller(painter, printer)
    assert_equal [0, 0], held_per_seller(painter, printer)
    assert_equal [40_500, 10_800], paid_out_per_seller(painter, printer)
  end

  test "a declined card returns the stock and a retry completes the order" do
    shop = create_seller
    art = create_listing(shop, price_cents: 45_000, quantity: 1)
    order = order_for(create_verified_customer, art)

    order.pay!(UNFUNDED_CARD, at: moment("2026-08-20 10:00:00"))

    assert_predicate order, :payment_failed?
    assert_equal "for_sale", art.reload.status
    assert_equal 1, art.quantity
    assert_equal 0, LedgerEntry.count

    order.pay!(APPROVED_CARD, at: moment("2026-08-20 10:05:00"))

    assert_predicate order, :paid?
    assert_equal "sold", art.reload.status
    assert_equal 0, art.quantity
    assert_equal %w[declined approved], order.payments.order(:id).pluck(:status)
    assert_equal [40_500], held_per_seller(shop)

    fulfillment = ship(order.fulfillments.sole, "USPS", "9400111899", "2026-08-21 11:00:00")
    deliver(fulfillment, "2026-08-22 09:00:00")

    payouts = Escrow::RunWeeklyPayout.new.call(as_of: moment("2026-08-24 09:00:00"))

    assert_equal [40_500], payouts.map(&:amount_cents)
    assert_equal [40_500], paid_out_per_seller(shop)
  end

  private

  def ship(fulfillment, carrier, tracking_number, at)
    fulfillment.ship!(carrier: carrier, tracking_number: tracking_number, at: moment(at))
  end

  def deliver(fulfillment, at)
    fulfillment.reload.deliver!(at: moment(at))
  end

  def held_per_seller(*sellers)
    sellers.map { |shop| balance_of(shop).held.cents }
  end

  def available_per_seller(*sellers)
    sellers.map { |shop| balance_of(shop).available.cents }
  end

  def paid_out_per_seller(*sellers)
    sellers.map { |shop| balance_of(shop).paid_out.cents }
  end
end
