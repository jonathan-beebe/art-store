require "commerce_test_case"

class CartTest < CommerceTestCase
  test "a cart reads as the lines checkout totals" do
    shop = seller
    cart = cart_holding(customer, listing(shop, price_cents: 45_000))

    line = cart.lines.sole

    assert_equal shop.id, line.seller_id
    assert_equal 45_000, line.unit_price.cents
    assert_equal 1, line.quantity
  end

  test "an empty cart has no lines" do
    assert_empty cart_for(customer).lines
  end
end
