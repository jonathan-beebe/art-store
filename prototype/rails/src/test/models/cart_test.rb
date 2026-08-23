require "test_helper"

class CartTest < ActiveSupport::TestCase
  test "a cart reads as the lines checkout totals" do
    shop = create_seller
    cart = cart_holding(create_verified_customer, create_listing(shop, price_cents: 45_000))

    line = cart.lines.sole

    assert_equal shop.id, line.seller_id
    assert_equal 45_000, line.unit_price.cents
    assert_equal 1, line.quantity
  end

  test "an empty cart has no lines" do
    assert_empty cart_for(create_verified_customer).lines
  end
end
