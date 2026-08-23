require "test_helper"

module Carts
  class RemoveFromCartTest < ActiveSupport::TestCase
    test "it takes the listing out of the cart" do
      shop = create_seller
      kept = create_listing(shop)
      dropped = create_listing(shop)
      cart = cart_holding(create_verified_customer, kept, dropped)

      RemoveFromCart.new.call(cart: cart, listing: dropped)

      assert_equal [kept], cart.reload.items.map(&:listing)
    end

    test "removing a listing the cart never held changes nothing" do
      cart = cart_holding(create_verified_customer, create_listing)

      RemoveFromCart.new.call(cart: cart, listing: create_listing)

      assert_equal 1, cart.reload.items.count
    end
  end
end
