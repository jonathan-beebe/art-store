require "commerce_test_case"

module Carts
  class AddToCartTest < CommerceTestCase
    test "it puts the listing in the cart" do
      art = listing(seller, quantity: 3)
      cart = cart_for(customer)

      item = AddToCart.new.call(cart: cart, listing: art, quantity: 2, now: moment("2026-08-20 08:00:00"))

      assert_equal 2, item.quantity
      assert_equal [art], cart.reload.items.map(&:listing)
    end

    test "adding the same listing again adds to the line" do
      art = listing(seller, quantity: 3)
      cart = cart_for(customer)
      add_to_cart = AddToCart.new
      add_to_cart.call(cart: cart, listing: art, quantity: 1, now: moment("2026-08-20 08:00:00"))

      add_to_cart.call(cart: cart, listing: art, quantity: 1, now: moment("2026-08-20 08:05:00"))

      assert_equal 1, cart.reload.items.count
      assert_equal 2, cart.items.sole.quantity
    end

    test "a cart never holds more than the seller has left" do
      art = listing(seller, quantity: 2)

      item = AddToCart.new.call(cart: cart_for(customer), listing: art, quantity: 5, now: moment("2026-08-20 08:00:00"))

      assert_equal 2, item.quantity
    end

    test "it refuses a sold out listing" do
      art = listing(seller, quantity: 0, status: Domain::Listings::ListingStatus::SOLD)

      assert_raises(ArgumentError) do
        AddToCart.new.call(cart: cart_for(customer), listing: art, quantity: 1, now: moment("2026-08-20 08:00:00"))
      end
    end

    test "it records the interest against the listing" do
      art = listing(seller)
      buyer = customer

      AddToCart.new.call(cart: cart_for(buyer), listing: art, quantity: 1, now: moment("2026-08-20 08:00:00"))

      event = art.listing_events.sole
      assert_equal Domain::Listings::ListingEventType::CART_ADD, event.event_type
      assert_equal buyer.id, event.customer_id
    end
  end
end
