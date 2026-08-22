require "commerce_test_case"

class CartTest < CommerceTestCase
  def test_a_cart_reads_as_the_lines_checkout_totals
    shop = seller
    cart = cart_holding(customer, listing(shop, price_cents: 45_000))

    line = cart.lines.sole

    assert_equal shop.id, line.seller_id
    assert_equal 45_000, line.unit_price.cents
    assert_equal 1, line.quantity
  end

  def test_an_empty_cart_has_no_lines
    assert_empty cart_for(customer).lines
  end
end
