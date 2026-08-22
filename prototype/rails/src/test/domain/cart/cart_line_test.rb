require "test_helper"

module Domain
  module Cart
    class CartLineTest < ActiveSupport::TestCase
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
