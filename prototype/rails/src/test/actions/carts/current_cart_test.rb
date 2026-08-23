require "test_helper"

class CurrentCartTest < ActiveSupport::TestCase
  setup do
    @current_cart = Carts::CurrentCart.new
  end

  test "a visitor with no cart gets one" do
    shopper = create_anonymous_customer

    cart = @current_cart.call(customer: shopper)

    assert_equal shopper, cart.customer
    assert_empty cart.items
  end

  test "it returns the same cart on a second call" do
    shopper = create_anonymous_customer

    assert_equal @current_cart.call(customer: shopper), @current_cart.call(customer: shopper)
  end

  test "a merged customer keeps shopping with the cart holding items" do
    shopper = create_verified_customer
    cart_for(shopper)
    filled = cart_holding(shopper, create_listing)

    assert_equal filled, @current_cart.call(customer: shopper)
  end
end
