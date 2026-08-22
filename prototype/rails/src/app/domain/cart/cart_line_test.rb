# Runs without Rails: ruby -Iapp app/domain/cart/cart_line_test.rb
require "minitest/autorun"
require_relative "cart_line"

module Domain
  module Cart
    class CartLineTest < Minitest::Test
      def test_a_line_totals_its_unit_price
        line = CartLine.new(seller_id: 1, unit_price: Money.from_cents(4500), quantity: 3)

        assert_equal 13_500, line.total.cents
      end

      def test_a_line_covers_at_least_one_item
        assert_raises(ArgumentError) { CartLine.new(seller_id: 1, unit_price: Money.from_cents(4500), quantity: 0) }
      end
    end
  end
end
