require "test_helper"

class CartItemTest < ActiveSupport::TestCase
  test "a line totals its unit price" do
    cart = cart_for(create_verified_customer)
    item = cart.add(create_listing(price_cents: 45_000, quantity: 3), quantity: 3)

    assert_equal 135_000, item.total.cents
  end

  test "a line covers at least one item" do
    item = CartItem.new(cart: cart_for(create_verified_customer), listing: create_listing, quantity: 0)

    assert_predicate item, :invalid?
  end
end
