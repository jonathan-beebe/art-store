require "commerce_test_case"

module Fulfillments
  class ConfirmDeliveredTest < CommerceTestCase
    test "it records when the order arrived" do
      fulfillment = deliver(shipped_fulfillment)

      assert_equal Domain::Orders::FulfillmentStatus::DELIVERED, fulfillment.status
      assert_equal moment("2026-08-22 09:00:00"), fulfillment.delivered_at
    end

    test "delivery releases the escrow the sale held" do
      shop = seller
      fulfillment = deliver(shipped_fulfillment(shop))

      entry = fulfillment.ledger_entries.find_by(entry_type: Domain::Escrow::LedgerEntryType::RELEASED)
      assert_equal 40_500, entry.amount_cents
      assert_equal shop.id, entry.seller_id
      assert_equal moment("2026-08-22 09:00:00"), entry.occurred_at
    end

    test "released money becomes available to the seller" do
      shop = seller

      deliver(shipped_fulfillment(shop))

      balance = balance_of(shop)
      assert_equal 0, balance.held.cents
      assert_equal 40_500, balance.available.cents
    end

    test "the last delivery of an order delivers the order" do
      fulfillment = shipped_fulfillment

      deliver(fulfillment)

      assert_equal Domain::Orders::OrderStatus::DELIVERED, fulfillment.order.reload.status
    end

    test "it refuses a fulfillment that has not shipped" do
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
