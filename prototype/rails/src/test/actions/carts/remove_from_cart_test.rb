require "commerce_test_case"

module Carts
  class RemoveFromCartTest < CommerceTestCase
    def test_it_takes_the_listing_out_of_the_cart
      shop = seller
      kept = listing(shop)
      dropped = listing(shop)
      cart = cart_holding(customer, kept, dropped)

      RemoveFromCart.new.call(cart: cart, listing: dropped)

      assert_equal [kept], cart.reload.items.map(&:listing)
    end

    def test_removing_a_listing_the_cart_never_held_changes_nothing
      cart = cart_holding(customer, listing(seller))

      RemoveFromCart.new.call(cart: cart, listing: listing(seller))

      assert_equal 1, cart.reload.items.count
    end
  end
end
