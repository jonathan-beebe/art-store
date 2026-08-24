require "test_helper"

class FulfillmentTest < ActiveSupport::TestCase
  test "a fulfillment starts out awaiting shipment" do
    assert_equal %w[awaiting_shipment shipped delivered declined refunded], Fulfillment.statuses.keys
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

  test "a delivered fulfillment can only be refunded" do
    assert_equal %w[refunded], Fulfillment::TRANSITIONS.fetch("delivered")
  end

  test "a declined or refunded fulfillment has nowhere left to go" do
    assert_empty Fulfillment::TRANSITIONS.fetch("declined")
    assert_empty Fulfillment::TRANSITIONS.fetch("refunded")
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

  test "declining stops the fulfillment and sends the subtotal back" do
    fulfillment = decline(awaiting_shipment)

    assert_predicate fulfillment, :declined?
    assert_predicate fulfillment, :reversed?

    refund = fulfillment.refunds.sole
    assert_equal 45_000, refund.amount_cents
    assert_equal "seller", refund.issued_by_type
    assert_equal fulfillment.seller_id, refund.issued_by_id
    assert_equal "The kiln cracked it.", fulfillment.refund_reason
  end

  test "declining puts the stock back on the storefront" do
    listing = create_listing(create_seller, quantity: 1)
    fulfillment = paid_order_for(create_verified_customer, listing).fulfillments.sole

    assert_equal "sold", listing.reload.status

    decline(fulfillment)

    assert_equal "for_sale", listing.reload.status
    assert_equal 1, listing.quantity
  end

  test "declining restores only the quantities that fulfillment claimed" do
    painter = create_seller
    printer = create_seller
    painting = create_listing(painter, quantity: 3)
    print = create_listing(printer, quantity: 3)
    order = paid_order_for(create_verified_customer, painting, print)

    decline(order.fulfillments.find_by!(seller: painter))

    assert_equal 3, painting.reload.quantity
    assert_equal 2, print.reload.quantity
  end

  test "declining takes the net back off the seller's balance" do
    shop = create_seller

    decline(awaiting_shipment(shop))

    balance = shop.escrow_balance
    assert_equal 0, balance.held.cents
    assert_equal 0, balance.available.cents
  end

  test "declining tells the customer what happened and why" do
    buyer = create_verified_customer
    fulfillment = paid_order_for(buyer, create_listing).fulfillments.sole

    decline(fulfillment)

    notification = buyer.notifications.where(subject: "Order refunded").sole
    assert_includes notification.body, "The kiln cracked it."
    assert_includes notification.body, "$450.00"
  end

  test "it refuses to decline a fulfillment that has shipped" do
    fulfillment = ship(awaiting_shipment)

    refusal = assert_raises(ActiveRecord::RecordInvalid) { decline(fulfillment) }

    assert_includes refusal.record.errors.full_messages, "A fulfillment cannot move from shipped to declined."
    assert_equal 0, Refund.count
  end

  test "it refuses to ship a fulfillment that was declined" do
    fulfillment = decline(awaiting_shipment)

    refusal = assert_raises(ActiveRecord::RecordInvalid) { ship(fulfillment) }

    assert_includes refusal.record.errors.full_messages, "A fulfillment cannot move from declined to shipped."
  end

  test "it refuses to decline the same fulfillment twice" do
    fulfillment = decline(awaiting_shipment)

    refusal = assert_raises(ActiveRecord::RecordInvalid) { decline(fulfillment) }

    assert_includes refusal.record.errors.full_messages, "A fulfillment cannot move from declined to declined."
    assert_equal 1, Refund.count
  end

  test "it refuses to decline a fulfillment nobody has paid for" do
    fulfillment = order_for(create_verified_customer, create_listing).fulfillments.sole

    refusal = assert_raises(ActiveRecord::RecordInvalid) { decline(fulfillment) }

    assert_includes refusal.record.errors.full_messages, Fulfillment::UNCHARGED
    assert_predicate fulfillment.reload, :awaiting_shipment?
  end

  test "refunding sends the subtotal back without moving the stock" do
    listing = create_listing(create_seller, quantity: 1)
    fulfillment = paid_order_for(create_verified_customer, listing).fulfillments.sole

    refund(fulfillment)

    assert_predicate fulfillment, :refunded?
    assert_equal 45_000, fulfillment.refunds.sole.amount_cents
    assert_equal "admin", fulfillment.refunds.sole.issued_by_type
    assert_equal "sold", listing.reload.status
    assert_equal 0, listing.quantity
  end

  test "the platform can refund a fulfillment at any point the customer holds money in" do
    assert_predicate refund(awaiting_shipment), :refunded?
    assert_predicate refund(ship(awaiting_shipment)), :refunded?
    assert_predicate refund(deliver(ship(awaiting_shipment))), :refunded?
  end

  test "refunding a delivered fulfillment takes back what delivery released" do
    shop = create_seller

    refund(deliver(ship(awaiting_shipment(shop))))

    balance = shop.escrow_balance
    assert_equal 0, balance.held.cents
    assert_equal 0, balance.available.cents
  end

  test "refunding tells both the customer and the seller" do
    shop = create_seller
    buyer = create_verified_customer
    fulfillment = paid_order_for(buyer, create_listing(shop)).fulfillments.sole

    refund(fulfillment)

    assert_equal "Order refunded", buyer.notifications.where(subject: "Order refunded").sole.subject
    assert_includes shop.notifications.where(subject: "Sale refunded").sole.body, "Dispute found for the buyer."
  end

  test "it refuses to refund the same fulfillment twice" do
    fulfillment = refund(awaiting_shipment)

    refusal = assert_raises(ActiveRecord::RecordInvalid) { refund(fulfillment) }

    assert_includes refusal.record.errors.full_messages, "A fulfillment cannot move from refunded to refunded."
    assert_equal 1, Refund.count
  end

  test "it refuses to refund a declined fulfillment" do
    fulfillment = decline(awaiting_shipment)

    refusal = assert_raises(ActiveRecord::RecordInvalid) { refund(fulfillment) }

    assert_includes refusal.record.errors.full_messages, "A fulfillment cannot move from declined to refunded."
  end

  test "it refuses to refund a fulfillment nobody has paid for" do
    fulfillment = order_for(create_verified_customer, create_listing).fulfillments.sole

    refusal = assert_raises(ActiveRecord::RecordInvalid) { refund(fulfillment) }

    assert_includes refusal.record.errors.full_messages, Fulfillment::UNCHARGED
    assert_equal 0, Refund.count
  end

  test "a refund with no reason leaves the fulfillment where it was" do
    fulfillment = awaiting_shipment

    assert_raises(ActiveRecord::RecordInvalid) { fulfillment.refund!(reason: " ", by: create_admin) }

    assert_predicate fulfillment.reload, :awaiting_shipment?
    assert_equal 0, Refund.count
    assert_equal 0, LedgerEntry.refunded.count
  end

  test "only a paid fulfillment that has not been reversed can be declined or refunded" do
    unpaid = order_for(create_verified_customer, create_listing).fulfillments.sole
    paid = awaiting_shipment

    refute_predicate unpaid, :declinable?
    refute_predicate unpaid, :refundable?
    assert_predicate paid, :declinable?
    assert_predicate paid, :refundable?
    refute_predicate ship(paid), :declinable?
    assert_predicate paid, :refundable?
  end

  test "a fulfillment nothing was reversed on carries no refund reason" do
    assert_nil awaiting_shipment.refund_reason
  end

  test "the platform forgoes its fee on a fulfillment whose money went back" do
    decline(awaiting_shipment)
    ship(awaiting_shipment)

    assert_equal 4_500, Fulfillment.fees_earned_cents
    assert_equal 4_500, Fulfillment.fees_refunded_cents
  end

  test "a fee nobody paid for is neither earned nor refunded" do
    order_for(create_verified_customer, create_listing)

    assert_equal 0, Fulfillment.fees_earned_cents
    assert_equal 0, Fulfillment.fees_refunded_cents
  end

  private

  def decline(fulfillment)
    fulfillment.decline!(
      reason: "The kiln cracked it.", by: fulfillment.seller, at: moment("2026-08-21 09:00:00")
    )
  end

  def refund(fulfillment)
    fulfillment.refund!(
      reason: "Dispute found for the buyer.", by: create_admin, at: moment("2026-08-23 09:00:00")
    )
  end

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
