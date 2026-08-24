require "test_helper"

class FulfillmentTest < ActiveSupport::TestCase
  test "a fulfillment starts out awaiting shipment" do
    assert_equal %w[awaiting_shipment shipped delivered], Fulfillment.statuses.keys
    assert_predicate awaiting_shipment, :awaiting_shipment?
  end

  test "every status has a transition list" do
    assert_equal Fulfillment.statuses.keys.sort, Fulfillment::TRANSITIONS.keys.sort
  end

  test "a fulfillment awaiting shipment can ship but cannot arrive" do
    fulfillment = Fulfillment.new(status: :awaiting_shipment)

    assert fulfillment.can_transition_to?(:shipped)
    refute fulfillment.can_transition_to?(:delivered)
  end

  test "a shipped fulfillment can be delivered" do
    assert Fulfillment.new(status: :shipped).can_transition_to?(:delivered)
  end

  test "a delivered fulfillment has nowhere left to go" do
    assert_empty Fulfillment::TRANSITIONS.fetch("delivered")
  end

  test "a fulfillment that has shipped or arrived has departed" do
    assert_predicate Fulfillment.new(status: :shipped), :departed?
    assert_predicate Fulfillment.new(status: :delivered), :departed?
    refute_predicate Fulfillment.new(status: :awaiting_shipment), :departed?
  end

  test "shipping records the carrier and the tracking number" do
    fulfillment = ship(awaiting_shipment)

    assert_predicate fulfillment, :shipped?
    assert_equal "USPS", fulfillment.carrier
    assert_equal "9400111899", fulfillment.tracking_number
    assert_equal moment("2026-08-21 11:00:00"), fulfillment.shipped_at
  end

  test "surrounding space is not part of the carrier or the tracking number" do
    fulfillment = ship(awaiting_shipment, carrier: "  Royal Mail  ", tracking_number: "  RM123456789GB  ")

    assert_equal "Royal Mail", fulfillment.carrier
    assert_equal "RM123456789GB", fulfillment.tracking_number
  end

  test "the only shipment of an order ships the order" do
    order = paid_order_for(create_verified_customer, create_listing)

    ship(order.fulfillments.sole)

    assert_equal "shipped", order.reload.status
  end

  test "one shipment of two partially ships the order" do
    order = paid_order_for(
      create_verified_customer,
      create_listing(create_seller(shop_name: "Blue Kiln Studio")),
      create_listing(create_seller(shop_name: "Rye Press"))
    )

    ship(order.fulfillments.first)

    assert_equal "partially_shipped", order.reload.status
  end

  test "shipping tells the customer how to track the order" do
    buyer = create_verified_customer
    order = paid_order_for(buyer, create_listing)

    ship(order.fulfillments.sole)

    notification = buyer.notifications.sole
    assert_equal "Order shipped", notification.subject
    assert_includes notification.body, "9400111899"
  end

  test "it refuses to ship the same fulfillment twice" do
    fulfillment = ship(awaiting_shipment)

    refusal = assert_raises(ActiveRecord::RecordInvalid) { ship(fulfillment) }

    assert_equal [ "A fulfillment cannot move from shipped to shipped." ], refusal.record.errors.full_messages
  end

  test "it refuses a shipment with no carrier" do
    fulfillment = awaiting_shipment

    refusal = assert_raises(ActiveRecord::RecordInvalid) { ship(fulfillment, carrier: " ") }

    assert_equal [ "A shipment needs a carrier and a tracking number." ], refusal.record.errors.full_messages
    assert_predicate fulfillment.reload, :awaiting_shipment?
  end

  test "it refuses a shipment with no tracking number" do
    fulfillment = awaiting_shipment

    refusal = assert_raises(ActiveRecord::RecordInvalid) { ship(fulfillment, tracking_number: "") }

    assert_equal [ "A shipment needs a carrier and a tracking number." ], refusal.record.errors.full_messages
    assert_predicate fulfillment.reload, :awaiting_shipment?
  end

  test "a missing field is refused rather than an error" do
    refusal = assert_raises(ActiveRecord::RecordInvalid) do
      awaiting_shipment.ship!(carrier: nil, tracking_number: nil)
    end

    assert_equal [ "A shipment needs a carrier and a tracking number." ], refusal.record.errors.full_messages
  end

  test "delivery records when the order arrived" do
    fulfillment = deliver(ship(awaiting_shipment))

    assert_predicate fulfillment, :delivered?
    assert_equal moment("2026-08-22 09:00:00"), fulfillment.delivered_at
  end

  test "delivery releases the escrow the sale held" do
    shop = create_seller
    fulfillment = deliver(ship(awaiting_shipment(shop)))

    entry = fulfillment.ledger_entries.released.sole
    assert_equal 40_500, entry.amount_cents
    assert_equal shop.id, entry.seller_id
    assert_equal moment("2026-08-22 09:00:00"), entry.occurred_at
  end

  test "released money becomes available to the seller" do
    shop = create_seller

    deliver(ship(awaiting_shipment(shop)))

    balance = shop.escrow_balance
    assert_equal 0, balance.held.cents
    assert_equal 40_500, balance.available.cents
  end

  test "the last delivery of an order delivers the order" do
    fulfillment = ship(awaiting_shipment)

    deliver(fulfillment)

    assert_equal "delivered", fulfillment.order.reload.status
  end

  test "it refuses to deliver a fulfillment that has not shipped" do
    refusal = assert_raises(ActiveRecord::RecordInvalid) { deliver(awaiting_shipment) }

    assert_equal [ "A fulfillment cannot move from awaiting_shipment to delivered." ],
                 refusal.record.errors.full_messages
  end

  test "a fulfillment splits its subtotal into the platform fee and the seller's net" do
    fulfillment = awaiting_shipment

    assert_equal 45_000, fulfillment.subtotal.cents
    assert_equal 4_500, fulfillment.fee.cents
    assert_equal 40_500, fulfillment.net.cents
  end

  test "the platform takes a tenth and the seller keeps the rest" do
    subtotal = Money.from_cents(45_000)

    assert_equal 4500, Fulfillment.fee_for(subtotal).cents
    assert_equal 40_500, Fulfillment.net_for(subtotal).cents
  end

  test "the fee and the net add back up" do
    subtotal = Money.from_cents(4999)

    assert_equal subtotal.cents, Fulfillment.fee_for(subtotal).cents + Fulfillment.net_for(subtotal).cents
  end

  test "half a cent of fee rounds away from zero" do
    assert_equal 5, Fulfillment.fee_for(Money.from_cents(45)).cents
  end

  test "nothing owes nothing" do
    assert_equal 0, Fulfillment.fee_for(Money.zero).cents
  end

  private

  def awaiting_shipment(shop = create_seller)
    paid_order_for(create_verified_customer, create_listing(shop)).fulfillments.sole
  end

  def ship(fulfillment, carrier: "USPS", tracking_number: "9400111899")
    fulfillment.ship!(carrier: carrier, tracking_number: tracking_number, at: moment("2026-08-21 11:00:00"))
  end

  def deliver(fulfillment)
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))
  end
end
