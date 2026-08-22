require "commerce_test_case"

# Walks the whole order lifecycle across two sellers. Every other action test
# covers one step; these two protect the way the steps add up.
module Orders
  class OrderLifecycleTest < CommerceTestCase
    def test_an_order_runs_from_the_cart_to_the_weekly_payout
      painter = seller("Blue Kiln Studio")
      printer = seller("Rye Press")
      painting = listing(painter, price_cents: 45_000, quantity: 1)
      print = listing(printer, price_cents: 12_000, quantity: 1)

      order = order_for(customer, painting, print)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.status
      assert_equal 57_000, order.total_cents
      assert_equal Domain::Listings::ListingStatus::SOLD, painting.reload.status

      FinalizeOrder.new.call(order: order, card_number: APPROVED_CARD, now: moment("2026-08-20 10:00:00"))

      assert_equal Domain::Orders::OrderStatus::PAID, order.status
      assert_equal 2, Notification.where(subject: "Item sold").count
      assert_equal [40_500, 10_800], held_per_seller(painter, printer)

      painting_shipment = order.fulfillments.find_by(seller: painter)
      print_shipment = order.fulfillments.find_by(seller: printer)

      ship(painting_shipment, "USPS", "9400111899", "2026-08-21 11:00:00")
      assert_equal Domain::Orders::OrderStatus::PARTIALLY_SHIPPED, order.reload.status

      ship(print_shipment, "FedEx", "7712349", "2026-08-21 12:00:00")
      assert_equal Domain::Orders::OrderStatus::SHIPPED, order.reload.status
      assert_equal 2, Notification.where(subject: "Order shipped").count

      deliver(painting_shipment, "2026-08-22 09:00:00")
      assert_equal Domain::Orders::OrderStatus::SHIPPED, order.reload.status

      deliver(print_shipment, "2026-08-22 10:00:00")
      assert_equal Domain::Orders::OrderStatus::DELIVERED, order.reload.status
      assert_equal [40_500, 10_800], available_per_seller(painter, printer)

      payouts = Escrow::RunWeeklyPayout.new.call(as_of: moment("2026-08-24 09:00:00"))

      assert_equal 2, payouts.size
      assert_equal({ painter.id => 40_500, printer.id => 10_800 }, Payout.order(:seller_id).pluck(:seller_id, :amount_cents).to_h)
      assert_equal [0, 0], available_per_seller(painter, printer)
      assert_equal [0, 0], held_per_seller(painter, printer)
      assert_equal [40_500, 10_800], paid_out_per_seller(painter, printer)
    end

    def test_a_declined_card_returns_the_stock_and_a_retry_completes_the_order
      shop = seller
      art = listing(shop, price_cents: 45_000, quantity: 1)
      order = order_for(customer, art)

      FinalizeOrder.new.call(order: order, card_number: UNFUNDED_CARD, now: moment("2026-08-20 10:00:00"))

      assert_equal Domain::Orders::OrderStatus::PAYMENT_FAILED, order.status
      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.reload.status
      assert_equal 1, art.quantity
      assert_equal 0, LedgerEntry.count

      FinalizeOrder.new.call(order: order, card_number: APPROVED_CARD, now: moment("2026-08-20 10:05:00"))

      assert_equal Domain::Orders::OrderStatus::PAID, order.status
      assert_equal Domain::Listings::ListingStatus::SOLD, art.reload.status
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
      Fulfillments::MarkShipped.new.call(
        fulfillment: fulfillment, carrier: carrier, tracking_number: tracking_number, now: moment(at)
      )
    end

    def deliver(fulfillment, at)
      Fulfillments::ConfirmDelivered.new.call(fulfillment: fulfillment.reload, now: moment(at))
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
end
