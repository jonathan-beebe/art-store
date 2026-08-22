require "commerce_test_case"

module Carts
  class RemoveFromCartTest < CommerceTestCase
    test "it takes the listing out of the cart" do
      shop = seller
      kept = listing(shop)
      dropped = listing(shop)
      cart = cart_holding(customer, kept, dropped)

      RemoveFromCart.new.call(cart: cart, listing: dropped)

      assert_equal [kept], cart.reload.items.map(&:listing)
    end

    test "removing a listing the cart never held changes nothing" do
      cart = cart_holding(customer, listing(seller))

      RemoveFromCart.new.call(cart: cart, listing: listing(seller))

      assert_equal 1, cart.reload.items.count
    end
  end
end
