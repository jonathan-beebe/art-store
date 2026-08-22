require "test_helper"

module Domain
  module Cart
    class CartTotalsTest < ActiveSupport::TestCase
      test "it counts every item" do
        assert_equal 3, CartTotals.from([line(1, 4500, 2), line(1, 1000, 1)]).item_count
      end

      test "it adds every line" do
        assert_equal 10_000, CartTotals.from([line(1, 4500, 2), line(2, 1000, 1)]).subtotal.cents
      end

      test "it splits the subtotal by seller" do
        totals = CartTotals.from([line(2, 1000, 1), line(1, 4500, 2), line(1, 500, 1)])

        assert_equal({ 1 => 9500, 2 => 1000 }, totals.subtotals_by_seller.transform_values(&:cents))
      end

      test "it orders the sellers by id" do
        totals = CartTotals.from([line(9, 1000, 1), line(3, 1000, 1)])

        assert_equal [3, 9], totals.subtotals_by_seller.keys
      end

      test "an empty cart totals nothing" do
        totals = CartTotals.from([])

        assert_equal 0, totals.item_count
        assert_equal 0, totals.subtotal.cents
        assert_empty totals.subtotals_by_seller
      end

      test "checkout refuses an empty cart" do
        assert_raises(ArgumentError) { CartTotals.for_checkout([]) }
      end

      test "checkout totals a cart that has something in it" do
        assert_equal 4500, CartTotals.for_checkout([line(1, 4500, 1)]).subtotal.cents
      end

      private

      def line(seller_id, unit_price_cents, quantity)
        CartLine.new(seller_id: seller_id, unit_price: Money.from_cents(unit_price_cents), quantity: quantity)
      end
    end
  end
end
