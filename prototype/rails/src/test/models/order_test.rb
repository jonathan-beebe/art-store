require "test_helper"

class OrderTest < ActiveSupport::TestCase
  test "an order totals in money" do
    order = order_for(create_verified_customer, create_listing(price_cents: 45_000))

    assert_equal "$450.00", order.total.format
  end

  test "it turns the cart into an order the customer can pay for" do
    order = place(create_verified_customer)

    assert_predicate order, :awaiting_payment?
    assert_equal 45_000, order.subtotal_cents
    assert_equal 45_000, order.total_cents
    assert_equal moment("2026-08-20 09:00:00"), order.placed_at
    assert_nil order.finalized_at
  end

  test "a guest places an order that waits for verification" do
    assert_predicate place(create_anonymous_customer), :pending_verification?
  end

  test "it copies the shipping address onto the order" do
    order = place(create_verified_customer)

    assert_equal "Ada Lovelace", order.shipping_name
    assert_equal "12 Analytical Way", order.shipping_line1
    assert_nil order.shipping_line2
    assert_equal "EC1A 1BB", order.shipping_postal_code
    assert_equal "GB", order.shipping_country
  end

  test "it snapshots the title and price of every item" do
    art = create_listing(title: "Harbour at Dusk", price_cents: 45_000)

    item = order_for(create_verified_customer, art).items.sole

    assert_equal "Harbour at Dusk", item.title
    assert_equal 45_000, item.unit_price_cents
    assert_equal art.seller_id, item.seller_id
  end

  test "it splits the order into one fulfillment per seller" do
    painting = create_listing(create_seller(shop_name: "Blue Kiln Studio"), price_cents: 45_000)
    print = create_listing(create_seller(shop_name: "Rye Press"), price_cents: 10_000)

    order = order_for(create_verified_customer, painting, print)

    assert_equal 55_000, order.subtotal_cents
    assert_equal(
      [[painting.seller_id, 45_000, 4500, 40_500], [print.seller_id, 10_000, 1000, 9000]],
      order.fulfillments.order(:seller_id).map { |f| [f.seller_id, f.subtotal_cents, f.fee_cents, f.net_cents] }
    )
  end

  test "every fulfillment starts awaiting shipment" do
    assert_predicate place(create_verified_customer).fulfillments.sole, :awaiting_shipment?
  end

  test "it takes the stock the order claims" do
    art = create_listing(quantity: 3)
    buyer = create_verified_customer
    cart = cart_for(buyer)
    cart.add(art, quantity: 2, at: moment("2026-08-20 08:00:00"))

    Order.place(cart: cart, customer: buyer, email: buyer.email, email_verified: true,
                shipping: shipping_address, at: moment("2026-08-20 09:00:00"))

    art.reload
    assert_equal 1, art.quantity
    assert_equal "for_sale", art.status
  end

  test "the last of a listing marks it sold" do
    art = create_listing(quantity: 1)

    order_for(create_verified_customer, art)

    art.reload
    assert_equal 0, art.quantity
    assert_equal "sold", art.status
  end

  test "it empties the cart" do
    buyer = create_verified_customer
    cart = cart_holding(buyer, create_listing)

    Order.place(cart: cart, customer: buyer, email: buyer.email, email_verified: true,
                shipping: shipping_address, at: moment("2026-08-20 09:00:00"))

    assert_empty cart.reload.items
  end

  test "it refuses an empty cart" do
    buyer = create_verified_customer

    assert_raises(ArgumentError) do
      Order.place(cart: cart_for(buyer), customer: buyer, email: buyer.email, email_verified: true,
                  shipping: shipping_address, at: moment("2026-08-20 09:00:00"))
    end
  end

  test "an incomplete address opens no order and leaves the cart alone" do
    buyer = create_verified_customer
    cart = cart_holding(buyer, create_listing)

    order = Order.place(cart: cart, customer: buyer, email: buyer.email, email_verified: true,
                        shipping: shipping_address(shipping_city: nil), at: moment("2026-08-20 09:00:00"))

    refute_predicate order, :persisted?
    assert_equal 1, cart.reload.items.count
  end

  test "an approved card pays the order" do
    order = pay(order_for(create_verified_customer, create_listing), APPROVED_CARD)

    assert_predicate order, :paid?
    assert_equal moment("2026-08-20 10:00:00"), order.finalized_at
  end

  test "an approved card records the payment" do
    order = pay(order_for(create_verified_customer, create_listing), APPROVED_CARD)

    payment = order.payments.sole
    assert_predicate payment, :approved?
    assert_equal 45_000, payment.amount_cents
    assert_equal "4242", payment.card_last_four
    assert_nil payment.decline_reason
    assert_equal moment("2026-08-20 10:00:00"), payment.processed_at
  end

  test "a paid order holds the seller net in escrow" do
    shop = create_seller
    order = pay(order_for(create_verified_customer, create_listing(shop)), APPROVED_CARD)

    entry = LedgerEntry.sole
    assert_predicate entry, :held?
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

    pay(order, APPROVED_CARD)

    assert_equal [9000, 40_500], LedgerEntry.order(:amount_cents).pluck(:amount_cents)
  end

  test "a paid order tells each seller their item sold" do
    shop = create_seller

    pay(order_for(create_verified_customer, create_listing(shop)), APPROVED_CARD)

    notification = Notification.sole
    assert_equal shop, notification.recipient
    assert_equal "Item sold", notification.subject
    assert_includes notification.body, "$405.00"
  end

  test "a declined card fails the payment" do
    order = pay(order_for(create_verified_customer, create_listing), DECLINED_CARD)

    assert_predicate order, :payment_failed?
    assert_nil order.finalized_at
    assert_equal "generic_decline", order.payments.sole.decline_reason
  end

  test "a declined card puts the stock back on the storefront" do
    art = create_listing(quantity: 1)

    pay(order_for(create_verified_customer, art), DECLINED_CARD)

    art.reload
    assert_equal 1, art.quantity
    assert_equal "for_sale", art.status
  end

  test "a declined card holds nothing and tells nobody" do
    pay(order_for(create_verified_customer, create_listing), DECLINED_CARD)

    assert_equal 0, LedgerEntry.count
    assert_equal 0, Notification.count
  end

  test "a retry with a good card pays the order and takes the stock again" do
    art = create_listing(quantity: 1)
    order = order_for(create_verified_customer, art)
    pay(order, DECLINED_CARD)

    pay(order, APPROVED_CARD, at: "2026-08-20 10:05:00")

    art.reload
    assert_predicate order, :paid?
    assert_equal 0, art.quantity
    assert_equal "sold", art.status
    assert_equal 2, order.payments.count
    assert_equal 40_500, LedgerEntry.sole.amount_cents
  end

  test "a retry that is declined again leaves the stock on the storefront" do
    art = create_listing(quantity: 1)
    order = order_for(create_verified_customer, art)
    pay(order, DECLINED_CARD)

    pay(order, UNFUNDED_CARD, at: "2026-08-20 10:05:00")

    art.reload
    assert_predicate order, :payment_failed?
    assert_equal 1, art.quantity
    assert_equal "for_sale", art.status
    assert_equal "insufficient_funds", order.payments.last.decline_reason
  end

  test "it refuses to charge an order that is already paid" do
    order = pay(order_for(create_verified_customer, create_listing), APPROVED_CARD)

    assert_raises(Domain::TransitionError) { pay(order, APPROVED_CARD, at: "2026-08-20 10:05:00") }
  end

  test "it refuses to charge an order that has not been verified" do
    order = order_for(create_anonymous_customer, create_listing)

    assert_raises(Domain::TransitionError) { pay(order, APPROVED_CARD) }
  end

  test "verifying opens payment on a guest order" do
    order = order_for(create_anonymous_customer, create_listing)

    order.mark_awaiting_payment!

    assert_predicate order.reload, :awaiting_payment?
  end

  test "an order that already awaits payment stays where it is" do
    order = order_for(create_verified_customer, create_listing)

    order.mark_awaiting_payment!

    assert_predicate order.reload, :awaiting_payment?
  end

  test "an order whose fulfillments all await shipment stays paid" do
    order = paid_order_for(create_verified_customer, create_listing)

    order.roll_up_status!

    assert_predicate order.reload, :paid?
  end

  test "one shipped fulfillment of two partially ships the order" do
    order = paid_order_for(
      create_verified_customer,
      create_listing(create_seller(shop_name: "Blue Kiln Studio")),
      create_listing(create_seller(shop_name: "Rye Press"))
    )
    order.fulfillments.first.update!(status: "shipped")

    order.roll_up_status!

    assert_predicate order.reload, :partially_shipped?
  end

  test "a delivered fulfillment beside an unshipped one is still partially shipped" do
    order = paid_order_for(
      create_verified_customer,
      create_listing(create_seller(shop_name: "Blue Kiln Studio")),
      create_listing(create_seller(shop_name: "Rye Press"))
    )
    order.fulfillments.first.update!(status: "delivered")

    order.roll_up_status!

    assert_predicate order.reload, :partially_shipped?
  end

  test "every fulfillment departed ships the order" do
    order = paid_order_for(
      create_verified_customer,
      create_listing(create_seller(shop_name: "Blue Kiln Studio")),
      create_listing(create_seller(shop_name: "Rye Press"))
    )
    order.fulfillments.first.update!(status: "shipped")
    order.fulfillments.last.update!(status: "delivered")

    order.roll_up_status!

    assert_predicate order.reload, :shipped?
  end

  test "every fulfillment delivered delivers the order" do
    order = paid_order_for(create_verified_customer, create_listing)
    order.fulfillments.sole.update!(status: "delivered")

    order.roll_up_status!

    assert_predicate order.reload, :delivered?
  end

  test "an order rolls up from at least one fulfillment" do
    order = order_for(create_verified_customer, create_listing)
    order.fulfillments.destroy_all

    assert_raises(ArgumentError) { order.roll_up_status! }
  end

  test "every status has a transition list" do
    assert_equal Order.statuses.keys.sort, Order::TRANSITIONS.keys.sort
  end

  test "verifying an email opens payment" do
    assert_equal "awaiting_payment", Order.transition("pending_verification", "awaiting_payment")
  end

  test "an order awaiting payment is paid or fails" do
    assert_equal "paid", Order.transition("awaiting_payment", "paid")
    assert_equal "payment_failed", Order.transition("awaiting_payment", "payment_failed")
  end

  test "a guest cannot pay before verifying" do
    assert_raises(Domain::TransitionError) { Order.transition("pending_verification", "paid") }
  end

  test "a failed payment retries" do
    assert_equal "paid", Order.transition("payment_failed", "paid")
  end

  test "a retry that is declined again stays where it was" do
    assert_equal "payment_failed", Order.transition("payment_failed", "payment_failed")
  end

  test "a paid order ships whole or in part" do
    assert_equal "shipped", Order.transition("paid", "shipped")
    assert_equal "partially_shipped", Order.transition("paid", "partially_shipped")
  end

  test "a delivered order goes nowhere" do
    assert_empty Order::TRANSITIONS.fetch("delivered")
  end

  test "a cancelled order goes nowhere" do
    assert_empty Order::TRANSITIONS.fetch("cancelled")
  end

  test "a paid order cannot be paid twice" do
    error = assert_raises(Domain::TransitionError) { Order.transition("paid", "paid") }

    assert_equal "An order cannot move from paid to paid.", error.message
  end

  test "an order needs an email and a full shipping address" do
    assert_predicate placeable, :valid?
  end

  test "the second address line is optional" do
    assert_predicate placeable(shipping_line2: ""), :valid?
    assert_nil placeable(shipping_line2: "").shipping_line2
  end

  test "a blank shipping part is refused" do
    order = placeable(shipping_city: "   ", shipping_country: nil)

    refute_predicate order, :valid?
    assert_equal %i[shipping_city shipping_country], order.errors.attribute_names
  end

  test "a missing shipping address is refused" do
    order = Order.new(customer: create_anonymous_customer, email: "ada@example.test")

    refute_predicate order, :valid?
    assert_equal Order::REQUIRED_SHIPPING_FIELDS, order.errors.attribute_names
  end

  test "an address that is not an email is refused" do
    refute_predicate placeable(email: "ada"), :valid?
  end

  test "it normalizes the address the buyer typed" do
    assert_equal "ada@example.test", placeable(email: " Ada@Example.Test ").email
  end

  test "it strips the shipping address the buyer typed" do
    assert_equal "London", placeable(shipping_city: "  London  ").shipping_city
  end

  test "an order awaiting payment takes a card" do
    assert_predicate order_with_status("awaiting_payment"), :awaits_card?
  end

  test "a declined order takes another card" do
    assert_predicate order_with_status("payment_failed"), :awaits_card?
  end

  test "an unverified order takes no card yet" do
    refute_predicate order_with_status("pending_verification"), :awaits_card?
  end

  test "a paid order takes no card" do
    refute_predicate order_with_status("paid"), :awaits_card?
  end

  test "an unverified order is unpaid" do
    assert_predicate order_with_status("pending_verification"), :unpaid?
  end

  test "a declined order is unpaid" do
    assert_predicate order_with_status("payment_failed"), :unpaid?
  end

  test "a shipped order is not unpaid" do
    refute_predicate order_with_status("shipped"), :unpaid?
  end

  test "a signed-in customer pays an order awaiting payment" do
    assert order_with_status("awaiting_payment").payable_by?(true)
  end

  test "a customer who is not signed in pays nothing" do
    refute order_with_status("awaiting_payment").payable_by?(false)
  end

  test "a signed-in customer cannot pay a delivered order" do
    refute order_with_status("delivered").payable_by?(true)
  end

  private

  def place(buyer)
    order_for(buyer, create_listing(price_cents: 45_000))
  end

  def pay(order, card_number, at: "2026-08-20 10:00:00")
    order.pay!(card_number, at: moment(at))
  end

  def order_with_status(status)
    Order.new(status: status)
  end

  # An order as checkout hands it over, before it is placed.
  def placeable(email: "ada@example.test", **overrides)
    Order.new(customer: create_anonymous_customer, email: email, **shipping_address(**overrides))
  end
end
