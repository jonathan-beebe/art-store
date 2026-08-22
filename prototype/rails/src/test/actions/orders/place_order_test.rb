require "commerce_test_case"

module Orders
  class PlaceOrderTest < CommerceTestCase
    test "it turns the cart into an order the customer can pay for" do
      order = place(customer)

      assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.status
      assert_equal 45_000, order.subtotal_cents
      assert_equal 45_000, order.total_cents
      assert_equal moment("2026-08-20 09:00:00"), order.placed_at
      assert_nil order.finalized_at
    end

    test "a guest places an order that waits for verification" do
      assert_equal Domain::Orders::OrderStatus::PENDING_VERIFICATION, place(anonymous_customer).status
    end

    test "it copies the shipping address onto the order" do
      order = place(customer)

      assert_equal "Ada Lovelace", order.shipping_name
      assert_equal "12 Analytical Way", order.shipping_line1
      assert_nil order.shipping_line2
      assert_equal "EC1A 1BB", order.shipping_postal_code
      assert_equal "GB", order.shipping_country
    end

    test "it snapshots the title and price of every item" do
      art = listing(seller, title: "Harbour at Dusk", price_cents: 45_000)

      item = order_for(customer, art).items.sole

      assert_equal "Harbour at Dusk", item.title
      assert_equal 45_000, item.unit_price_cents
      assert_equal art.seller_id, item.seller_id
    end

    test "it splits the order into one fulfillment per seller" do
      painting = listing(seller("Blue Kiln Studio"), price_cents: 45_000)
      print = listing(seller("Rye Press"), price_cents: 10_000)

      order = order_for(customer, painting, print)

      assert_equal 55_000, order.subtotal_cents
      assert_equal(
        [[painting.seller_id, 45_000, 4500, 40_500], [print.seller_id, 10_000, 1000, 9000]],
        order.fulfillments.order(:seller_id).map { |f| [f.seller_id, f.subtotal_cents, f.fee_cents, f.net_cents] }
      )
    end

    test "every fulfillment starts awaiting shipment" do
      status = place(customer).fulfillments.sole.status

      assert_equal Domain::Orders::FulfillmentStatus::AWAITING_SHIPMENT, status
    end

    test "it takes the stock the order claims" do
      art = listing(seller, quantity: 3)
      cart = cart_for(customer)
      Carts::AddToCart.new.call(cart: cart, listing: art, quantity: 2, now: moment("2026-08-20 08:00:00"))

      PlaceOrder.new.call(cart: cart, purchaser: purchaser(cart.customer), shipping: shipping_address,
                          now: moment("2026-08-20 09:00:00"))

      art.reload
      assert_equal 1, art.quantity
      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.status
    end

    test "the last of a listing marks it sold" do
      art = listing(seller, quantity: 1)

      order_for(customer, art)

      art.reload
      assert_equal 0, art.quantity
      assert_equal Domain::Listings::ListingStatus::SOLD, art.status
    end

    test "it empties the cart" do
      buyer = customer
      cart = cart_holding(buyer, listing(seller))

      PlaceOrder.new.call(cart: cart, purchaser: purchaser(buyer), shipping: shipping_address,
                          now: moment("2026-08-20 09:00:00"))

      assert_empty cart.reload.items
    end

    test "it refuses an empty cart" do
      buyer = customer

      assert_raises(ArgumentError) do
        PlaceOrder.new.call(cart: cart_for(buyer), purchaser: purchaser(buyer), shipping: shipping_address,
                            now: moment("2026-08-20 09:00:00"))
      end
    end

    private

    def place(buyer)
      order_for(buyer, listing(seller, price_cents: 45_000))
    end
  end
end
