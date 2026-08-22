require "test_helper"

module Domain
  module Cart
    class CartLineTest < ActiveSupport::TestCase
      test "a line totals its unit price" do
        line = CartLine.new(seller_id: 1, unit_price: Money.from_cents(4500), quantity: 3)

        assert_equal 13_500, line.total.cents
      end

      test "a line covers at least one item" do
        assert_raises(ArgumentError) { CartLine.new(seller_id: 1, unit_price: Money.from_cents(4500), quantity: 0) }
      end
    end
  end
end
