require "test_helper"

module Orders
  class FinalizeOrderTest < ActiveSupport::TestCase
    test "an approved card pays the order" do
      order = finalize(order_for(create_verified_customer, create_listing), APPROVED_CARD)

      assert_equal Domain::Orders::OrderStatus::PAID, order.status
      assert_equal moment("2026-08-20 10:00:00"), order.finalized_at
    end

    test "an approved card records the payment" do
      order = finalize(order_for(create_verified_customer, create_listing), APPROVED_CARD)

      payment = order.payments.sole
      assert_equal Domain::Payments::PaymentStatus::APPROVED, payment.status
      assert_equal 45_000, payment.amount_cents
      assert_equal "4242", payment.card_last_four
      assert_nil payment.decline_reason
    end

    test "a paid order holds the seller net in escrow" do
      shop = create_seller
      order = finalize(order_for(create_verified_customer, create_listing(shop)), APPROVED_CARD)

      entry = LedgerEntry.sole
      assert_equal Domain::Escrow::LedgerEntryType::HELD, entry.entry_type
      assert_equal 40_500, entry.amount_cents
      assert_equal shop.id, entry.seller_id
      assert_equal order.fulfillments.sole.id, entry.fulfillment_id
    end

    test "a paid order holds one amount per seller" do
      order = order_for(
        create_verified_customer,
        create_listing(create_seller(shop_name: "Blue Kiln Studio")),
        create_listing(create_seller(shop_name: "Rye Press"), price_cents: 10_000)
      )

      finalize(order, APPROVED_CARD)

      assert_equal [9000, 40_500], LedgerEntry.order(:amount_cents).pluck(:amount_cents)
    end

    test "a paid order tells each seller their item sold" do
      shop = create_seller

      finalize(order_for(create_verified_customer, create_listing(shop)), APPROVED_CARD)

      notification = Notification.sole
      assert_equal shop.id, notification.seller_id
      assert_equal "Item sold", notification.subject
      assert_includes notification.body, "$405.00"
    end

    test "a declined card fails the payment" do
      order = finalize(order_for(create_verified_customer, create_listing), DECLINED_CARD)

      assert_equal Domain::Orders::OrderStatus::PAYMENT_FAILED, order.status
      assert_nil order.finalized_at
      assert_equal Domain::Payments::DeclineReason::GENERIC_DECLINE, order.payments.sole.decline_reason
    end

    test "a declined card puts the stock back on the storefront" do
      art = create_listing(quantity: 1)

      finalize(order_for(create_verified_customer, art), DECLINED_CARD)

      art.reload
      assert_equal 1, art.quantity
      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.status
    end

    test "a declined card holds nothing and tells nobody" do
      finalize(order_for(create_verified_customer, create_listing), DECLINED_CARD)

      assert_equal 0, LedgerEntry.count
      assert_equal 0, Notification.count
    end

    test "a retry with a good card pays the order and takes the stock again" do
      art = create_listing(quantity: 1)
      order = order_for(create_verified_customer, art)
      finalize(order, DECLINED_CARD)

      finalize(order, APPROVED_CARD, at: "2026-08-20 10:05:00")

      art.reload
      assert_equal Domain::Orders::OrderStatus::PAID, order.status
      assert_equal 0, art.quantity
      assert_equal Domain::Listings::ListingStatus::SOLD, art.status
      assert_equal 2, order.payments.count
      assert_equal 40_500, LedgerEntry.sole.amount_cents
    end

    test "a retry that is declined again leaves the stock on the storefront" do
      art = create_listing(quantity: 1)
      order = order_for(create_verified_customer, art)
      finalize(order, DECLINED_CARD)

      finalize(order, UNFUNDED_CARD, at: "2026-08-20 10:05:00")

      art.reload
      assert_equal Domain::Orders::OrderStatus::PAYMENT_FAILED, order.status
      assert_equal 1, art.quantity
      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.status
      assert_equal Domain::Payments::DeclineReason::INSUFFICIENT_FUNDS, order.payments.last.decline_reason
    end

    test "it refuses to charge an order that is already paid" do
      order = finalize(order_for(create_verified_customer, create_listing), APPROVED_CARD)

      assert_raises(Domain::TransitionError) { finalize(order, APPROVED_CARD, at: "2026-08-20 10:05:00") }
    end

    test "it refuses to charge an order that has not been verified" do
      order = order_for(create_anonymous_customer, create_listing)

      assert_raises(Domain::TransitionError) { finalize(order, APPROVED_CARD) }
    end

    private

    def finalize(order, card_number, at: "2026-08-20 10:00:00")
      FinalizeOrder.new.call(order: order, card_number: card_number, now: moment(at))
    end
  end
end
