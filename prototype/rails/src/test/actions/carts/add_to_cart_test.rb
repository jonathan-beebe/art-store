require "commerce_test_case"

module Carts
  class AddToCartTest < CommerceTestCase
    def test_it_puts_the_listing_in_the_cart
      art = listing(seller, quantity: 3)
      cart = cart_for(customer)

      item = AddToCart.new.call(cart: cart, listing: art, quantity: 2, now: moment("2026-08-20 08:00:00"))

      assert_equal 2, item.quantity
      assert_equal [art], cart.reload.items.map(&:listing)
    end

    def test_adding_the_same_listing_again_adds_to_the_line
      art = listing(seller, quantity: 3)
      cart = cart_for(customer)
      add_to_cart = AddToCart.new
      add_to_cart.call(cart: cart, listing: art, quantity: 1, now: moment("2026-08-20 08:00:00"))

      add_to_cart.call(cart: cart, listing: art, quantity: 1, now: moment("2026-08-20 08:05:00"))

      assert_equal 1, cart.reload.items.count
      assert_equal 2, cart.items.sole.quantity
    end

    def test_a_cart_never_holds_more_than_the_seller_has_left
      art = listing(seller, quantity: 2)

      item = AddToCart.new.call(cart: cart_for(customer), listing: art, quantity: 5, now: moment("2026-08-20 08:00:00"))

      assert_equal 2, item.quantity
    end

    def test_it_refuses_a_sold_out_listing
      art = listing(seller, quantity: 0, status: Domain::Listings::ListingStatus::SOLD)

      assert_raises(ArgumentError) do
        AddToCart.new.call(cart: cart_for(customer), listing: art, quantity: 1, now: moment("2026-08-20 08:00:00"))
      end
    end

    def test_it_records_the_interest_against_the_listing
      art = listing(seller)
      buyer = customer

      AddToCart.new.call(cart: cart_for(buyer), listing: art, quantity: 1, now: moment("2026-08-20 08:00:00"))

      event = art.listing_events.sole
      assert_equal Domain::Listings::ListingEventType::CART_ADD, event.event_type
      assert_equal buyer.id, event.customer_id
    end
  end
end
