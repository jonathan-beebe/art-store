require "commerce_test_case"

class CurrentCartTest < CommerceTestCase
  def setup
    @current_cart = Carts::CurrentCart.new
  end

  def test_a_visitor_with_no_cart_gets_one
    shopper = anonymous_customer

    cart = @current_cart.call(customer: shopper)

    assert_equal shopper, cart.customer
    assert_empty cart.items
  end

  def test_it_returns_the_same_cart_on_a_second_call
    shopper = anonymous_customer

    assert_equal @current_cart.call(customer: shopper), @current_cart.call(customer: shopper)
  end

  def test_a_merged_customer_keeps_shopping_with_the_cart_holding_items
    shopper = customer
    cart_for(shopper)
    filled = cart_holding(shopper, listing(seller))

    assert_equal filled, @current_cart.call(customer: shopper)
  end
end
