require "commerce_test_case"

module Fulfillments
  class ConfirmDeliveredTest < CommerceTestCase
    def test_it_records_when_the_order_arrived
      fulfillment = deliver(shipped_fulfillment)

      assert_equal Domain::Orders::FulfillmentStatus::DELIVERED, fulfillment.status
      assert_equal moment("2026-08-22 09:00:00"), fulfillment.delivered_at
    end

    def test_delivery_releases_the_escrow_the_sale_held
      shop = seller
      fulfillment = deliver(shipped_fulfillment(shop))

      entry = fulfillment.ledger_entries.find_by(entry_type: Domain::Escrow::LedgerEntryType::RELEASED)
      assert_equal 40_500, entry.amount_cents
      assert_equal shop.id, entry.seller_id
      assert_equal moment("2026-08-22 09:00:00"), entry.occurred_at
    end

    def test_released_money_becomes_available_to_the_seller
      shop = seller

      deliver(shipped_fulfillment(shop))

      balance = balance_of(shop)
      assert_equal 0, balance.held.cents
      assert_equal 40_500, balance.available.cents
    end

    def test_the_last_delivery_of_an_order_delivers_the_order
      fulfillment = shipped_fulfillment

      deliver(fulfillment)

      assert_equal Domain::Orders::OrderStatus::DELIVERED, fulfillment.order.reload.status
    end

    def test_it_refuses_a_fulfillment_that_has_not_shipped
      fulfillment = paid_order_for(customer, listing(seller)).fulfillments.sole

      assert_raises(Domain::TransitionError) { deliver(fulfillment) }
    end

    private

    def shipped_fulfillment(shop = seller)
      MarkShipped.new.call(
        fulfillment: paid_order_for(customer, listing(shop)).fulfillments.sole,
        carrier: "USPS", tracking_number: "9400111899", now: moment("2026-08-21 11:00:00")
      )
    end

    def deliver(fulfillment)
      ConfirmDelivered.new.call(fulfillment: fulfillment, now: moment("2026-08-22 09:00:00"))
    end
  end
end
