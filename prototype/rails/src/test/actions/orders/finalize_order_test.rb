require "commerce_test_case"

module Orders
  class FinalizeOrderTest < CommerceTestCase
    def test_an_approved_card_pays_the_order
      order = finalize(order_for(customer, listing(seller)), APPROVED_CARD)

      assert_equal Domain::Orders::OrderStatus::PAID, order.status
      assert_equal moment("2026-08-20 10:00:00"), order.finalized_at
    end

    def test_an_approved_card_records_the_payment
      order = finalize(order_for(customer, listing(seller)), APPROVED_CARD)

      payment = order.payments.sole
      assert_equal Domain::Payments::PaymentStatus::APPROVED, payment.status
      assert_equal 45_000, payment.amount_cents
      assert_equal "4242", payment.card_last_four
      assert_nil payment.decline_reason
    end

    def test_a_paid_order_holds_the_seller_net_in_escrow
      shop = seller
      order = finalize(order_for(customer, listing(shop)), APPROVED_CARD)

      entry = LedgerEntry.sole
      assert_equal Domain::Escrow::LedgerEntryType::HELD, entry.entry_type
      assert_equal 40_500, entry.amount_cents
      assert_equal shop.id, entry.seller_id
      assert_equal order.fulfillments.sole.id, entry.fulfillment_id
    end

    def test_a_paid_order_holds_one_amount_per_seller
      order = order_for(customer, listing(seller("Blue Kiln Studio")), listing(seller("Rye Press"), price_cents: 10_000))

      finalize(order, APPROVED_CARD)

      assert_equal [9000, 40_500], LedgerEntry.order(:amount_cents).pluck(:amount_cents)
    end

    def test_a_paid_order_tells_each_seller_their_item_sold
      shop = seller

      finalize(order_for(customer, listing(shop)), APPROVED_CARD)

      notification = Notification.sole
      assert_equal shop.id, notification.seller_id
      assert_equal "Item sold", notification.subject
      assert_includes notification.body, "$405.00"
    end

    def test_a_declined_card_fails_the_payment
      order = finalize(order_for(customer, listing(seller)), DECLINED_CARD)

      assert_equal Domain::Orders::OrderStatus::PAYMENT_FAILED, order.status
      assert_nil order.finalized_at
      assert_equal Domain::Payments::DeclineReason::GENERIC_DECLINE, order.payments.sole.decline_reason
    end

    def test_a_declined_card_puts_the_stock_back_on_the_storefront
      art = listing(seller, quantity: 1)

      finalize(order_for(customer, art), DECLINED_CARD)

      art.reload
      assert_equal 1, art.quantity
      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.status
    end

    def test_a_declined_card_holds_nothing_and_tells_nobody
      finalize(order_for(customer, listing(seller)), DECLINED_CARD)

      assert_equal 0, LedgerEntry.count
      assert_equal 0, Notification.count
    end

    def test_a_retry_with_a_good_card_pays_the_order_and_takes_the_stock_again
      art = listing(seller, quantity: 1)
      order = order_for(customer, art)
      finalize(order, DECLINED_CARD)

      finalize(order, APPROVED_CARD, at: "2026-08-20 10:05:00")

      art.reload
      assert_equal Domain::Orders::OrderStatus::PAID, order.status
      assert_equal 0, art.quantity
      assert_equal Domain::Listings::ListingStatus::SOLD, art.status
      assert_equal 2, order.payments.count
      assert_equal 40_500, LedgerEntry.sole.amount_cents
    end

    def test_a_retry_that_is_declined_again_leaves_the_stock_on_the_storefront
      art = listing(seller, quantity: 1)
      order = order_for(customer, art)
      finalize(order, DECLINED_CARD)

      finalize(order, UNFUNDED_CARD, at: "2026-08-20 10:05:00")

      art.reload
      assert_equal Domain::Orders::OrderStatus::PAYMENT_FAILED, order.status
      assert_equal 1, art.quantity
      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.status
      assert_equal Domain::Payments::DeclineReason::INSUFFICIENT_FUNDS, order.payments.last.decline_reason
    end

    def test_it_refuses_to_charge_an_order_that_is_already_paid
      order = finalize(order_for(customer, listing(seller)), APPROVED_CARD)

      assert_raises(Domain::TransitionError) { finalize(order, APPROVED_CARD, at: "2026-08-20 10:05:00") }
    end

    def test_it_refuses_to_charge_an_order_that_has_not_been_verified
      order = order_for(anonymous_customer, listing(seller))

      assert_raises(Domain::TransitionError) { finalize(order, APPROVED_CARD) }
    end

    private

    def finalize(order, card_number, at: "2026-08-20 10:00:00")
      FinalizeOrder.new.call(order: order, card_number: card_number, now: moment(at))
    end
  end
end
