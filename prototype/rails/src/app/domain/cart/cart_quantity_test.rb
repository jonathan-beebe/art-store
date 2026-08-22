# Runs without Rails: ruby -Iapp app/domain/cart/cart_quantity_test.rb
require "minitest/autorun"
require_relative "cart_quantity"

module Domain
  module Cart
    class CartQuantityTest < Minitest::Test
      def test_it_takes_what_was_asked_for_when_the_stock_covers_it
        assert_equal 2, CartQuantity.within_stock(requested: 2, available: 3)
      end

      def test_it_stops_at_what_is_left
        assert_equal 3, CartQuantity.within_stock(requested: 5, available: 3)
      end

      def test_it_holds_at_least_one_of_a_listing
        assert_raises(ArgumentError) { CartQuantity.within_stock(requested: 0, available: 3) }
      end

      def test_it_refuses_a_sold_out_listing
        assert_raises(ArgumentError) { CartQuantity.within_stock(requested: 1, available: 0) }
      end
    end
  end
end
